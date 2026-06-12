<?php

namespace App\Http\Controllers;

use App\Models\HomepageFeaturedPhone;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomepageController extends Controller
{
    public function index(): View
    {
        return view('homepage.index', [
            'featuredPhones' => HomepageFeaturedPhone::query()
                ->with('product')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'products' => Product::query()
                ->where('status', 'published')
                ->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(specs, '$.saledate')) AS UNSIGNED) DESC")
                ->orderBy('brand')
                ->orderBy('name')
                ->get(['id', 'brand', 'name', 'soc_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('status', 'published'),
                Rule::unique('homepage_featured_phones', 'product_id'),
            ],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', Rule::in(['1'])],
        ]);

        DB::transaction(function () use ($validated, $request) {
            HomepageFeaturedPhone::query()->increment('sort_order', 10);

            HomepageFeaturedPhone::create([
                'product_id' => $validated['product_id'],
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
                'sort_order' => 0,
                'is_active' => $request->boolean('is_active', true),
            ]);
        });

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型已添加。');
    }

    public function update(Request $request, HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', Rule::in(['1'])],
        ]);

        $featuredPhone->update([
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型已更新。');
    }

    public function destroy(HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        $featuredPhone->delete();

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型已移除。');
    }

    public function moveUp(HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        return $this->move($featuredPhone, -1);
    }

    public function moveDown(HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        return $this->move($featuredPhone, 1);
    }

    private function move(HomepageFeaturedPhone $featuredPhone, int $direction): RedirectResponse
    {
        $featuredPhones = HomepageFeaturedPhone::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $featuredPhones->search(fn (HomepageFeaturedPhone $item) => $item->is($featuredPhone));
        $targetIndex = $index === false ? -1 : $index + $direction;

        if ($index === false || $targetIndex < 0 || $targetIndex >= $featuredPhones->count()) {
            return redirect()
                ->route('homepage.index')
                ->with('status', $direction < 0 ? '这个热门机型已经在最前面。' : '这个热门机型已经在最后面。');
        }

        $ids = $featuredPhones->pluck('id')->all();
        [$ids[$index], $ids[$targetIndex]] = [$ids[$targetIndex], $ids[$index]];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                HomepageFeaturedPhone::whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型顺序已更新。');
    }
}
