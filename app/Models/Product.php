<?php

namespace App\Models;

use App\Support\PhoneCatalog;
use App\Support\SafeUrl;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'source_key',
        'source_file',
        'source_id',
        'brand',
        'name',
        'slug',
        'image_url',
        'price',
        'soc_name',
        'battery_capacity',
        'status',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'battery_capacity' => 'integer',
            'release_date' => 'integer',
            'specs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            $product->release_date = self::deriveReleaseDate($product->specs);
            $product->search_text = self::deriveSearchText($product);
            $product->slug_key = self::deriveSlugKey($product);
        });
    }

    /**
     * Restrict a query to products matching the (alias-expanded) keyword.
     *
     * Two drivers (config `catalog.search.driver`):
     *
     * - `like` (default): case-insensitive `LIKE %term%` against the
     *   denormalized `search_text` column. A leading-wildcard LIKE is a full
     *   scan no B-tree can accelerate — fine for the current catalog size and
     *   bounded by the public API throttle.
     * - `fulltext`: MySQL FULLTEXT (ngram parser, see migration
     *   2026_07_16_000002) BOOLEAN-MODE phrase match per expanded term.
     *   Chinese-safe via ngram. Terms shorter than the ngram token size (2)
     *   and non-MySQL connections fall back to LIKE per-term, so search keeps
     *   working (degraded, never broken) if the driver is misconfigured.
     *
     * Both drivers OR the expanded terms (brand aliases, SoC aliases, 骁龙/
     * 闪充-style keywords from PhoneCatalog::expandSearchKeywords), preserving
     * the existing search semantics.
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        $keywords = PhoneCatalog::expandSearchKeywords($keyword);

        if ($keywords === []) {
            return $query;
        }

        $useFulltext = config('catalog.search.driver') === 'fulltext'
            && $query->getConnection()->getDriverName() === 'mysql';

        return $query->where(function (Builder $query) use ($keywords, $useFulltext): void {
            foreach ($keywords as $term) {
                if ($useFulltext && mb_strlen($term) >= 2) {
                    // Quoted phrase in BOOLEAN MODE; strip embedded quotes so a
                    // crafted keyword cannot alter the boolean expression.
                    $phrase = '"'.str_replace(['"', '\\'], ' ', mb_strtolower($term)).'"';
                    $query->orWhereRaw('MATCH(search_text) AGAINST (? IN BOOLEAN MODE)', [$phrase]);
                } else {
                    $query->orWhere('search_text', 'like', '%'.mb_strtolower($term).'%');
                }

                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
            }
        });
    }

    public static function deriveReleaseDate(mixed $specs): ?int
    {
        $date = (int) data_get($specs, 'saledate', 0);

        return $date > 0 ? $date : null;
    }

    public static function deriveSearchText(self $product): string
    {
        $specs = $product->specs ?? [];

        $parts = [
            $product->name,
            $product->brand,
            $product->soc_name,
            $product->source_id,
            data_get($specs, 'phonename'),
            data_get($specs, 'company'),
            data_get($specs, 'socname'),
            data_get($specs, 'cpu'),
            data_get($specs, 'gpu'),
            data_get($specs, 'feature'),
        ];

        $raw = trim(implode(' ', array_filter(
            array_map(fn ($value) => trim((string) $value), $parts),
            fn (string $value) => $value !== '',
        )));

        return mb_strtolower(trim($raw.' '.PhoneCatalog::compactKeyword($raw)));
    }

    /**
     * Canonical, idempotent normalization for the detail lookup key: lower-case,
     * collapse whitespace/slashes to single dashes, trim dashes. Kept in one
     * place so the persisted `slug_key` and an incoming request slug are always
     * compared on identical terms.
     */
    public static function normalizeSlug(?string $value): string
    {
        return (string) Str::of(rawurldecode((string) $value))
            ->lower()
            ->replaceMatches('/[\s\/]+/u', '-')
            ->replaceMatches('/-+/u', '-')
            ->trim('-');
    }

    /**
     * The value stored in `slug_key`: the normalized canonical slug, falling
     * back to the name when a row has no slug yet (e.g. before ensureSlug runs).
     */
    public static function deriveSlugKey(self $product): string
    {
        $base = trim((string) $product->slug) !== '' ? (string) $product->slug : (string) $product->name;

        return self::normalizeSlug($base);
    }

    /**
     * Product totals grouped by status in a single query.
     *
     * @return array{total: int, published: int, draft: int}
     */
    public static function statusCounts(): array
    {
        $counts = static::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'published' => (int) ($counts['published'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
        ];
    }

    public function getDisplayPriceAttribute(): string
    {
        $price = trim((string) $this->price);

        if ($price === '' || in_array($price, ['0', '0.0', '0.00'], true)) {
            return '暂无价格';
        }

        return $price;
    }

    public function getSafeImageUrlAttribute(): string
    {
        return self::safeImageUrl($this->image_url);
    }

    public static function safeImageUrl(?string $url): string
    {
        $url = trim((string) $url);
        $placeholder = asset('assets/phone-placeholder.svg');

        // Browsers fold "\" to "/", so any backslash can turn a "relative"
        // path into an off-site protocol-relative URL; control characters are
        // classic scheme-obfuscation. Reject both outright.
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return $placeholder;
        }

        if (str_starts_with($url, '/')) {
            // Site-relative only: "//host" (protocol-relative) is rejected by
            // the shared SafeUrl rules.
            return SafeUrl::passes($url) ? $url : $placeholder;
        }

        // Bare path without a scheme (e.g. "img/a.png") -> serve from this app.
        if (! preg_match('/^[a-z][a-z\d+\-.]*:/i', $url)) {
            return asset(ltrim($url, '/'));
        }

        // Absolute URL: must be http(s) with a host (SafeUrl) AND a
        // same-site/local host — images never load from arbitrary origins.
        if (! SafeUrl::passes($url)) {
            return $placeholder;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $allowedHosts = array_filter(['localhost', '127.0.0.1', '::1', $appHost]);

        if ($host && in_array($host, $allowedHosts, true)) {
            return $url;
        }

        return $placeholder;
    }

    /**
     * @return array<string, mixed>
     */
    public function specsForEditing(): array
    {
        if (! $this->exists && empty($this->specs)) {
            return [];
        }

        return self::syncSpecsWithFields($this->specs ?? [], [
            'id' => $this->exists ? $this->getKey() : null,
            'brand' => $this->brand,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'soc_name' => $this->soc_name,
            'battery_capacity' => $this->battery_capacity,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $specs
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function syncSpecsWithFields(?array $specs, array $fields): array
    {
        $specs ??= [];

        if (($fields['id'] ?? null) !== null) {
            $specs['id'] = (int) $fields['id'];
        }

        $specs['company'] = trim((string) ($fields['brand'] ?? ''));
        $specs['phonename'] = trim((string) ($fields['name'] ?? ''));
        $specs['imgurl'] = trim((string) ($fields['image_url'] ?? ''));
        $specs['price'] = self::numericSpecValue($fields['price'] ?? 0);
        $specs['socname'] = trim((string) ($fields['soc_name'] ?? ''));
        $specs['battery'] = self::numericSpecValue($fields['battery_capacity'] ?? 0);

        return $specs;
    }

    private static function numericSpecValue(mixed $value): int|float|string
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = trim((string) $value);

        if (! is_numeric($value)) {
            return $value;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
}
