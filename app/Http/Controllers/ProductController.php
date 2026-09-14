<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductData;
use App\Services\ProductImport;
use App\Services\ProductWriter;
use App\Support\PhoneCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);
        $products = Product::query()
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query->search($keyword))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('updated_at')->orderByDesc('release_date')->orderByDesc('id')
            ->paginate(15)->withQueryString();
        $counts = Product::statusCounts();

        return view('products.index', [
            'products' => $products,
            'hasActiveFilters' => $request->filled('keyword') || $request->filled('status'),
            'totalProducts' => $counts['total'], 'publishedProducts' => $counts['published'], 'draftProducts' => $counts['draft'],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', ['product' => new Product(['status' => 'draft']), 'brands' => PhoneCatalog::brands()]);
    }

    public function importForm(): View
    {
        $this->authorize('create', Product::class);

        return view('products.import');
    }

    public function import(Request $request, ProductImport $import): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.ProductImport::MAX_FILES],
            'files.*' => ['required', 'file', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $count = $import->import($request->file('files'), $validated['status']);

        return redirect()->route('products.index')->with('status', '批量导入完成，共新增 '.$count.' 个手机。');
    }

    public function store(Request $request, ProductData $data, ProductWriter $writer): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $writer->save($data->fromForm($request->all()));

        return redirect()->route('products.index')->with('status', '手机已创建。');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.edit', ['product' => $product, 'brands' => PhoneCatalog::brands()]);
    }

    public function update(Request $request, Product $product, ProductData $data, ProductWriter $writer): RedirectResponse
    {
        $this->authorize('update', $product);
        $writer->save($data->fromForm($request->all(), $product), $product);

        return redirect()->route('products.index')->with('status', '手机已更新。');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return redirect()->route('products.index')->with('status', '手机已删除。');
    }
}
