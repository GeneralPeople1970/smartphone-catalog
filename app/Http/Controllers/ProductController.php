<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\PhoneCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $hasActiveFilters = $request->filled('keyword') || $request->filled('status');

        $products = Product::query()
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $this->applyKeywordFilter($query, (string) $request->query('keyword'));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('updated_at')
            ->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(specs, '$.saledate')) AS UNSIGNED) DESC")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'hasActiveFilters' => $hasActiveFilters,
            'totalProducts' => Product::count(),
            'publishedProducts' => Product::where('status', 'published')->count(),
            'draftProducts' => Product::where('status', 'draft')->count(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product(['status' => 'draft']),
            'brands' => PhoneCatalog::brands(),
        ]);
    }

    public function importForm(): View
    {
        return view('products.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:10240'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        $records = [];
        $errors = [];

        foreach ($request->file('files', []) as $file) {
            if (strtolower($file->getClientOriginalExtension()) !== 'json') {
                $errors[] = $file->getClientOriginalName().' 不是 JSON 文件。';

                continue;
            }

            $content = preg_replace('/^\xEF\xBB\xBF/', '', $file->getContent()) ?? '';
            $items = json_decode($content, true);

            if (! is_array($items)) {
                $errors[] = $file->getClientOriginalName().' 解析失败，请确认根节点是 JSON 数组。';

                continue;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    $errors[] = $file->getClientOriginalName().' 第 '.($index + 1).' 条不是对象。';

                    continue;
                }

                $id = (int) ($item['id'] ?? 0);

                if ($id < 1) {
                    $errors[] = $file->getClientOriginalName().' 第 '.($index + 1).' 条缺少有效 id。';

                    continue;
                }

                $records[] = $this->normalizeImportedRecord($item, $file->getClientOriginalName(), $index);
            }
        }

        $ids = collect($records)->pluck('id')->all();
        $duplicateIdsInFiles = collect($ids)
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys()
            ->values()
            ->all();

        if ($duplicateIdsInFiles !== []) {
            $errors[] = '导入文件内部存在重复 ID：'.implode('、', $duplicateIdsInFiles);
        }

        $existingIds = Product::whereIn('id', $ids)->pluck('id')->all();

        if ($existingIds !== []) {
            $errors[] = '数据库已存在这些手机 ID，已停止导入：'.implode('、', $existingIds);
        }

        $sourceKeys = collect($records)->pluck('source_key')->all();
        $existingSourceKeys = Product::whereIn('source_key', $sourceKeys)
            ->get(['source_id', 'name'])
            ->map(fn (Product $product) => $product->source_id.' '.$product->name)
            ->all();

        if ($existingSourceKeys !== []) {
            $errors[] = '数据库已存在相同来源的数据，已停止导入：'.implode('、', $existingSourceKeys);
        }

        if ($errors !== []) {
            return back()
                ->withInput()
                ->withErrors(['files' => implode("\n", $errors)]);
        }

        DB::transaction(function () use ($records, $validated) {
            foreach ($records as $record) {
                $product = new Product;
                $product->id = $record['id'];
                $product->fill(Arr::except($record, ['id']));
                $product->status = $validated['status'];
                $product->save();
                $this->ensureSlug($product);
            }
        });

        return redirect()
            ->route('products.index')
            ->with('status', '批量导入完成，共新增 '.count($records).' 个手机。');
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $product = Product::create($this->validatedData($request));
            $this->ensureSlug($product);
        });

        return redirect()
            ->route('products.index')
            ->with('status', '手机已创建。');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'brands' => PhoneCatalog::brands(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($this->validatedData($request, $product));
            $this->ensureSlug($product);
        });

        return redirect()
            ->route('products.index')
            ->with('status', '手机已更新。');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('status', '手机已删除。');
    }

    private function applyKeywordFilter(Builder $query, string $keyword): void
    {
        $keywords = PhoneCatalog::expandSearchKeywords($keyword);

        if ($keywords === []) {
            return;
        }

        $query->where(function (Builder $query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $like = '%'.$keyword.'%';
                $compact = PhoneCatalog::compactKeyword($keyword);

                $query->orWhere('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('soc_name', 'like', $like)
                    ->orWhere('source_id', 'like', $like)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.phonename')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.company')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.socname')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.cpu')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.gpu')) LIKE ?", [$like])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.feature')) LIKE ?", [$like]);

                if (ctype_digit($keyword)) {
                    $query->orWhere('id', (int) $keyword);
                }

                if ($compact !== $keyword) {
                    $compactLike = '%'.$compact.'%';

                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(brand, ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(soc_name, ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(specs, '$.phonename')), ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(specs, '$.socname')), ' ', ''), '-', ''), '_', '') LIKE ?", [$compactLike]);
                }
            }
        });
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        if ($request->filled('slug')) {
            $slug = $this->normalizeSlug((string) $request->string('slug'));
            $request->merge(['slug' => $slug === '' ? null : $slug]);
        }

        $validated = $request->validate([
            'brand' => ['required', 'string', 'max:191', Rule::in(PhoneCatalog::brandInputValues())],
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('products', 'slug')->ignore($product),
            ],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'price' => ['nullable', 'string', 'max:100'],
            'soc_name' => ['nullable', 'string', 'max:191'],
            'battery_capacity' => ['nullable', 'integer', 'min:0', 'max:30000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'specs_text' => ['nullable', 'json'],
        ]);

        $validated['brand'] = PhoneCatalog::canonicalBrandName($validated['brand']);
        $validated['image_url'] = $this->nullableCleanText($validated['image_url'] ?? null);
        $validated['price'] = $this->normalizePrice($validated['price'] ?? null);
        $validated['battery_capacity'] = $this->normalizeBattery($validated['battery_capacity'] ?? null);

        $specs = Arr::get($validated, 'specs_text');
        $validated['specs'] = Product::syncSpecsWithFields(
            $specs ? json_decode($specs, true) : [],
            [
                'id' => $product?->getKey(),
                'brand' => $validated['brand'],
                'name' => $validated['name'],
                'image_url' => $validated['image_url'] ?? null,
                'price' => $validated['price'] ?? null,
                'soc_name' => $validated['soc_name'] ?? null,
                'battery_capacity' => $validated['battery_capacity'] ?? null,
            ]
        );

        return Arr::except($validated, ['specs_text']);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeImportedRecord(array $item, string $fileName, int $index): array
    {
        $sourceId = (int) $item['id'];
        $sourceName = pathinfo($fileName, PATHINFO_FILENAME);
        $sourceBrand = $this->cleanText($item['company'] ?? $sourceName);
        $brand = PhoneCatalog::canonicalBrandName($sourceBrand, $fileName);
        $sourceFile = PhoneCatalog::canonicalSourceFile($brand, $fileName) ?? $fileName;
        $name = $this->cleanText($item['phonename'] ?? $item['name'] ?? $brand.'-'.($index + 1));
        $specs = $item;

        if ($sourceBrand !== '' && $sourceBrand !== $brand) {
            $specs['source_company'] ??= $sourceBrand;
        }

        if ($sourceFile !== $fileName) {
            $specs['source_file_original'] ??= $fileName;
        }

        return [
            'id' => $sourceId,
            'source_key' => sha1($fileName.'|'.$sourceId.'|'.$name),
            'source_file' => $sourceFile,
            'source_id' => (string) $sourceId,
            'brand' => $brand,
            'name' => $name,
            'slug' => null,
            'image_url' => $this->nullableCleanText($item['imgurl'] ?? $item['image'] ?? ''),
            'price' => $this->normalizePrice($item['price'] ?? null),
            'soc_name' => $this->cleanText($item['socname'] ?? $item['processor'] ?? ''),
            'battery_capacity' => $this->normalizeBattery($item['battery'] ?? null),
            'specs' => $specs,
        ];
    }

    private function cleanText(mixed $value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function nullableCleanText(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text === '' ? null : $text;
    }

    private function normalizePrice(mixed $value): ?string
    {
        $price = $this->cleanText($value);

        return $price === '' || in_array($price, ['0', '0.0', '0.00', '暂无', '暂无价格', '暂无报价', '待定'], true) ? null : $price;
    }

    private function normalizeBattery(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_float($value)) {
            return $value > 0 ? (int) $value : null;
        }

        $text = $this->cleanText($value);

        if ($text === '') {
            return null;
        }

        if (ctype_digit($text)) {
            $battery = (int) $text;

            return $battery > 0 ? $battery : null;
        }

        preg_match('/(\d{3,5})\s*mAh/i', $text, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function ensureSlug(Product $product): void
    {
        $base = trim((string) $product->slug);

        if ($base === '') {
            $brandCode = strtolower(PhoneCatalog::codeForBrand($product->brand));
            $nameSlug = Str::slug($product->name);
            $base = trim($brandCode.'-'.$product->id.($nameSlug ? '-'.$nameSlug : ''), '-');
        }

        $slug = $this->uniqueSlug($this->normalizeSlug($base), $product);

        if ($slug !== $product->slug) {
            $product->forceFill([
                'slug' => $slug,
                'specs' => Product::syncSpecsWithFields($product->specs ?? [], [
                    'id' => $product->getKey(),
                    'brand' => $product->brand,
                    'name' => $product->name,
                    'image_url' => $product->image_url,
                    'price' => $product->price,
                    'soc_name' => $product->soc_name,
                    'battery_capacity' => $product->battery_capacity,
                ]),
            ])->save();
        }
    }

    private function normalizeSlug(string $value): string
    {
        $slug = Str::slug($value);

        if ($slug !== '') {
            return Str::limit($slug, 180, '');
        }

        $fallback = strtolower(PhoneCatalog::compactKeyword($value));
        $fallback = preg_replace('/[^\p{Han}a-z0-9]+/iu', '-', $fallback) ?? '';

        return trim(Str::limit($fallback, 180, ''), '-');
    }

    private function uniqueSlug(string $base, Product $product): string
    {
        $base = $base !== '' ? $base : 'phone-'.$product->getKey();
        $slug = $base;
        $index = 2;

        while (Product::query()
            ->where('slug', $slug)
            ->where('id', '!=', $product->getKey())
            ->exists()) {
            $suffix = '-'.$index;
            $slug = Str::limit($base, 191 - strlen($suffix), '').$suffix;
            $index++;
        }

        return $slug;
    }
}
