<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiFields;
use App\Http\Controllers\Api\Concerns\ValidatesApiQuery;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ListCursor;
use App\Support\PhoneFields;
use App\Support\PhoneQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

class PhoneController extends Controller
{
    use ResolvesApiFields;
    use ValidatesApiQuery;

    public function index(Request $request): JsonResponse
    {
        $this->validateApiQuery($request, $this->phoneQueryRules());
        $fields = $this->requestedFields($request, PhoneFields::LIST, PhoneFields::ALIASES, $this->allowedFields());
        $limit = $request->filled('limit') ? (int) $request->query('limit') : 500;
        if ($limit < 1) {
            abort(response()->json(['message' => 'limit 至少为 1。'], 422));
        }
        $limit = min($limit, 500);
        $query = $this->buildListQuery($request);
        $total = (clone $query)->count();
        PhoneQuery::order($query->select(PhoneFields::columns($fields)));

        if ($request->filled('cursor') || $request->query('paginate') === 'cursor') {
            return $this->cursorResponse($request, $query, $fields, $limit, $total);
        }
        $page = max((int) $request->query('page', 1), 1);

        return response()->json(
            $query->forPage($page, $limit)->get()->map(fn (Product $product) => $this->toItem($product, $fields))->values()
        )->withHeaders([
            'X-Total-Count' => $total, 'X-Per-Page' => $limit,
            'X-Current-Page' => $page, 'X-Pagination-Mode' => 'page',
        ]);
    }

    private function buildListQuery(Request $request): Builder
    {
        return Product::query()->where('status', 'published')
            ->when($request->filled('brand'), fn (Builder $query) => PhoneQuery::brand($query, $request->query('brand')))
            ->when($request->filled('ids'), function (Builder $query) use ($request) {
                $ids = collect($this->parseList($request->query('ids')))->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)->take(100)->all();
                if ($ids !== []) {
                    $query->whereIn('id', $ids);
                }
            })
            ->when($request->filled('name') || $request->filled('names'), function (Builder $query) use ($request) {
                $names = array_slice(array_unique([
                    ...$this->parseList($request->query('name')),
                    ...$this->parseList($request->query('names')),
                ]), 0, 100);
                if ($names !== []) {
                    $query->whereIn('name', $names);
                }
            })
            ->when($request->filled('q'), fn (Builder $query) => $query->search(mb_substr($request->query('q'), 0, 191)));
    }

    private function cursorResponse(Request $request, Builder $query, array $fields, int $limit, int $total): JsonResponse
    {
        $cursor = null;
        if ($request->filled('cursor')) {
            $key = ListCursor::decode($request->query('cursor'));
            if ($key === null) {
                abort(response()->json(['message' => 'cursor 无效。'], 422));
            }
            $cursor = new Cursor(['date_missing' => $key['f'], 'date_order' => $key['rd'], 'name' => $key['n'], 'id' => $key['id']]);
        }
        $page = $query->cursorPaginate($limit, ['*'], 'cursor', $cursor);
        $next = $page->nextCursor();
        $nextCursor = $next ? ListCursor::encode([
            'f' => (int) $next->parameter('date_missing'), 'rd' => (int) $next->parameter('date_order'),
            'n' => $next->parameter('name'), 'id' => (int) $next->parameter('id'),
        ]) : null;

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $product) => $this->toItem($product, $fields))->values(),
            'meta' => ['nextCursor' => $nextCursor, 'hasMore' => $page->hasMorePages(), 'perPage' => $limit, 'total' => $total],
        ])->withHeaders(['X-Total-Count' => $total, 'X-Per-Page' => $limit, 'X-Pagination-Mode' => 'cursor']);
    }

    public function search(Request $request): JsonResponse
    {
        $this->validateApiQuery($request, [...$this->phoneQueryRules(), 'q' => ['required', 'string', 'max:191']]);
        if (! $request->filled('fields')) {
            $request->query->set('fields', 'id,phonename,company,companyCode,socname,price,displayPrice,battery,imgurl,slug,brandLogo');
        }
        if (! $request->filled('limit')) {
            $request->query->set('limit', 20);
        }

        return $this->index($request);
    }

    public function brandSearch(Request $request, string $brand): JsonResponse
    {
        $request->query->set('brand', $brand);

        return $this->search($request);
    }

    public function show(Request $request, Product $phone): JsonResponse
    {
        abort_unless($phone->status === 'published', 404);

        return response()->json($this->toItem($phone, $this->requestedFields(
            $request, [...PhoneFields::LIST, ...PhoneFields::SPECS], PhoneFields::ALIASES, $this->allowedFields()
        )));
    }

    public function detail(Request $request): JsonResponse
    {
        $this->validateApiQuery($request, [...$this->phoneQueryRules(), 'slug' => ['required', 'string', 'max:191']]);
        $product = Product::query()->where('status', 'published')
            ->where('slug_key', Product::normalizeSlug($request->query('slug')))
            ->when($request->filled('brand'), fn (Builder $query) => PhoneQuery::brand($query, $request->query('brand')))
            ->orderBy('id')->firstOrFail();

        return $this->show($request, $product);
    }

    private function toItem(Product $product, array $fields): array
    {
        return $this->onlyFields(PhoneFields::values($product), $fields);
    }

    private function allowedFields(): array
    {
        return [...PhoneFields::LIST, ...PhoneFields::SPECS, ...PhoneFields::EXTRA];
    }
}
