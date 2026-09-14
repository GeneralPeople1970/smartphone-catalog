<?php

namespace App\Http\Controllers;

use App\Models\HomepageFeaturedPhone;
use App\Models\Product;
use App\Services\HomepageOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomepageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', HomepageFeaturedPhone::class);

        return view('homepage.index', [
            'featuredPhones' => HomepageFeaturedPhone::query()
                ->with('product')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'selectedProduct' => is_scalar(old('product_id'))
                ? Product::where('status', 'published')->find(old('product_id'), ['id', 'brand', 'name'])
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HomepageFeaturedPhone::class);
        $request->merge(['_featured_form' => 'create']);

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('status', 'published'),
                Rule::unique('homepage_featured_phones', 'product_id'),
            ],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        app(HomepageOrder::class)->prepend(HomepageFeaturedPhone::class, [
            'product_id' => $validated['product_id'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型已添加。');
    }

    public function update(Request $request, HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        $this->authorize('update', $featuredPhone);
        $request->merge(['_featured_form' => (string) $featuredPhone->id]);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
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
        $this->authorize('delete', $featuredPhone);

        $featuredPhone->delete();

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型已移除。');
    }

    public function moveUp(HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        $this->authorize('update', $featuredPhone);

        return $this->move($featuredPhone, -1);
    }

    public function moveDown(HomepageFeaturedPhone $featuredPhone): RedirectResponse
    {
        $this->authorize('update', $featuredPhone);

        return $this->move($featuredPhone, 1);
    }

    private function move(HomepageFeaturedPhone $featuredPhone, int $direction): RedirectResponse
    {
        if (! app(HomepageOrder::class)->move($featuredPhone, $direction)) {
            return redirect()
                ->route('homepage.index')
                ->with('status', $direction < 0 ? '这个热门机型已经在最前面。' : '这个热门机型已经在最后面。');
        }

        return redirect()
            ->route('homepage.index')
            ->with('status', '热门机型顺序已更新。');
    }
}
