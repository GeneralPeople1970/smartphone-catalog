<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\PhoneCatalog;
use App\Support\SafeUrl;
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
    private const MAX_IMPORT_FILES = 20;

    private const MAX_IMPORT_TOTAL_BYTES = 10 * 1024 * 1024;

    private const MAX_IMPORT_RECORDS = 2000;

    private const MAX_JSON_DEPTH = 32;

    private const MAX_STRING_LENGTH = 5000;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $hasActiveFilters = $request->filled('keyword') || $request->filled('status');

        $products = Product::query()
            ->when($request->filled('keyword'), fn (Builder $query) => $query->search((string) $request->query('keyword')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('updated_at')
            ->orderByDesc('release_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = Product::statusCounts();

        return view('products.index', [
            'products' => $products,
            'hasActiveFilters' => $hasActiveFilters,
            'totalProducts' => $counts['total'],
            'publishedProducts' => $counts['published'],
            'draftProducts' => $counts['draft'],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', [
            'product' => new Product(['status' => 'draft']),
            'brands' => PhoneCatalog::brands(),
        ]);
    }

    public function importForm(): View
    {
        $this->authorize('create', Product::class);

        return view('products.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMPORT_FILES],
            'files.*' => ['required', 'file', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        $files = $request->file('files', []);

        if (collect($files)->sum(fn ($file) => $file->getSize()) > self::MAX_IMPORT_TOTAL_BYTES) {
            return back()->withInput()->withErrors([
                'files' => '上传文件总大小超过 '.(int) (self::MAX_IMPORT_TOTAL_BYTES / 1024 / 1024).'MB 限制。',
            ]);
        }

        $records = [];
        $errors = [];
        $processed = 0;

        foreach ($files as $file) {
            if (strtolower($file->getClientOriginalExtension()) !== 'json') {
                $errors[] = $file->getClientOriginalName().' 不是 JSON 文件。';

                continue;
            }

            $content = preg_replace('/^\xEF\xBB\xBF/', '', $file->getContent()) ?? '';
            $items = json_decode($content, true, self::MAX_JSON_DEPTH);

            if (! is_array($items) || ! array_is_list($items)) {
                $errors[] = $file->getClientOriginalName().' 解析失败，请确认根节点是 JSON 对象数组。';

                continue;
            }

            // Reject an oversized file outright so a pathological array cannot
            // drive an unbounded loop (or unbounded $errors) even when every
            // item is invalid.
            if (count($items) > self::MAX_IMPORT_RECORDS) {
                $errors[] = $file->getClientOriginalName().' 记录数超过单批上限 '.self::MAX_IMPORT_RECORDS.' 条。';

                continue;
            }

            foreach ($items as $index => $item) {
                // Bound total work (valid or invalid) across all files.
                if ($processed >= self::MAX_IMPORT_RECORDS) {
                    $errors[] = '导入记录总数超过上限 '.self::MAX_IMPORT_RECORDS.' 条。';

                    break 2;
                }

                $processed++;

                if (! is_array($item)) {
                    $errors[] = $file->getClientOriginalName().' 第 '.($index + 1).' 条不是对象。';

                    continue;
                }

                if ($this->hasOversizedString($item)) {
                    $errors[] = $file->getClientOriginalName().' 第 '.($index + 1).' 条包含过长的字段。';

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
        $this->authorize('create', Product::class);

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
        $this->authorize('update', $product);

        return view('products.edit', [
            'product' => $product,
            'brands' => PhoneCatalog::brands(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

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
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('status', '手机已删除。');
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

        if (isset($validated['specs']['official'])) {
            $validated['specs']['official'] = SafeUrl::sanitize((string) $validated['specs']['official']) ?? '';
        }

        return Arr::except($validated, ['specs_text']);
    }

    /**
     * Whether any string value in the (possibly nested) record exceeds the
     * per-field length cap.
     *
     * @param  array<mixed>  $item
     */
    private function hasOversizedString(array $item): bool
    {
        foreach ($item as $value) {
            if (is_string($value) && mb_strlen($value) > self::MAX_STRING_LENGTH) {
                return true;
            }

            if (is_array($value) && $this->hasOversizedString($value)) {
                return true;
            }
        }

        return false;
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

        if (isset($specs['official'])) {
            $specs['official'] = SafeUrl::sanitize((string) $specs['official']) ?? '';
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
