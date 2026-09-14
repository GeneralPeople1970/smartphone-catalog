<?php

namespace App\Support;

class PhoneCatalog
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private static ?array $brandsCache = null;

    /**
     * @var array<string, array<int, string>>|null
     */
    private static ?array $searchAliasesCache = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $matchValueIndex = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $sourceFileIndex = null;

    /**
     * @return array<int, array{name: string, code: string, displayName: string, logo: ?string, path: string, sort: int, sourceFile: string, aliases?: array<int, string>, legacyCodes?: array<int, string>, sourceFiles?: array<int, string>}>
     */
    public static function brands(): array
    {
        return self::$brandsCache ??= json_decode(
            file_get_contents(resource_path('data/brands.json')), true, flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<int, string>
     */
    public static function brandInputValues(): array
    {
        return collect(self::brands())
            ->flatMap(fn (array $item) => self::matchValues($item))
            ->unique(fn (string $value) => mb_strtolower($value))
            ->values()
            ->all();
    }

    public static function brandInputValuesFor(array $entry): array
    {
        return self::matchValues($entry);
    }

    /**
     * @return array<int, string>
     */
    public static function resolveBrandNames(?string $brand): array
    {
        $entry = self::entryForInput($brand);

        if ($entry === null) {
            $brand = trim((string) $brand);

            return $brand === '' ? [] : [$brand];
        }

        return array_values(array_unique(array_merge([
            $entry['name'],
            $entry['displayName'],
        ], $entry['aliases'] ?? [])));
    }

    /**
     * @return array{name: string, code: string, displayName: string, logo: ?string, path: string, sort: int, aliases?: array<int, string>, sourceFiles?: array<int, string>}|null
     */
    public static function entryForInput(?string $brand): ?array
    {
        $brand = trim((string) $brand);

        if ($brand === '') {
            return null;
        }

        self::buildIndexes();

        return self::$matchValueIndex[mb_strtolower($brand)] ?? null;
    }

    /**
     * @return array{name: string, code: string, displayName: string, logo: ?string, path: string, sort: int, aliases?: array<int, string>, sourceFiles?: array<int, string>}|null
     */
    public static function entryForProduct(string $brand, ?string $sourceFile): ?array
    {
        // A saved brand is authoritative; provenance never reassigns it.
        return self::entryForInput($brand);
    }

    public static function entryForSourceFile(?string $sourceFile): ?array
    {
        self::buildIndexes();

        return self::$sourceFileIndex[mb_strtolower(trim($sourceFile ?? ''))] ?? null;
    }

    public static function codeForBrand(string $brand): string
    {
        return self::entryForInput($brand)['code'] ?? strtoupper($brand);
    }

    public static function canonicalBrandName(?string $brand): string
    {
        return self::entryForInput($brand)['name'] ?? trim((string) $brand);
    }

    public static function importedBrandName(?string $brand, ?string $sourceFile): string
    {
        $entry = self::entryForInput($brand) ?? self::entryForSourceFile($sourceFile);

        return $entry['name'] ?? trim((string) $brand);
    }

    public static function canonicalSourceFile(?string $brand, ?string $sourceFile = null): ?string
    {
        $entry = self::entryForInput($brand) ?? self::entryForSourceFile($sourceFile);

        return $entry['sourceFile'] ?? $sourceFile;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function searchAliases(): array
    {
        return self::$searchAliasesCache ??= self::buildSearchAliases();
    }

    /**
     * @return array<int, string>
     */
    public static function expandSearchKeywords(string $keyword): array
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $keywords = [$keyword];
        $lowerKeyword = mb_strtolower($keyword);

        foreach (self::searchAliases() as $alias => $replacements) {
            $lowerAlias = mb_strtolower($alias);

            if (str_contains($lowerKeyword, $lowerAlias)) {
                foreach ($replacements as $replacement) {
                    $keywords[] = str_ireplace($alias, $replacement, $keyword);

                    if ($lowerKeyword === $lowerAlias) {
                        $keywords[] = $replacement;
                    }
                }
            }
        }

        return collect($keywords)
            ->flatMap(fn (string $value) => [$value, self::compactKeyword($value)])
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function compactKeyword(string $keyword): string
    {
        return preg_replace('/[\s\-_\/（）()【】\[\].,，。:：]+/u', '', $keyword) ?? $keyword;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function buildSearchAliases(): array
    {
        $brandAliases = collect(self::brands())
            ->flatMap(function (array $item) {
                return collect(self::matchValues($item))
                    ->mapWithKeys(fn (string $value) => [mb_strtolower($value) => array_values(array_unique(array_merge([$item['name']], $item['aliases'] ?? [])))]);
            })
            ->all();

        return array_merge($brandAliases, [
            'qualcomm snapdragon' => ['骁龙', '高通骁龙'],
            'snapdragon' => ['骁龙'],
            'qualcomm' => ['高通', '骁龙'],
            '高通骁龙' => ['骁龙', 'Qualcomm Snapdragon'],
            '高通' => ['骁龙', 'Qualcomm'],
            '骁龙' => ['Snapdragon', 'Qualcomm Snapdragon'],
            'dimensity' => ['天玑'],
            'mediatek' => ['联发科', '天玑'],
            '联发科' => ['天玑', 'MediaTek'],
            '天玑' => ['Dimensity', '联发科'],
            'kirin' => ['麒麟'],
            '麒麟' => ['Kirin'],
            'exynos' => ['猎户座'],
            'bionic' => ['仿生', '苹果 A'],
        ]);
    }

    /**
     * Build O(1) lookup maps over the brand catalog. First brand wins on any
     * shared match value or source file, matching the previous foreach order.
     */
    private static function buildIndexes(): void
    {
        if (self::$matchValueIndex !== null) {
            return;
        }

        self::$matchValueIndex = [];
        self::$sourceFileIndex = [];

        foreach (self::brands() as $item) {
            foreach ($item['sourceFiles'] ?? [] as $file) {
                self::$sourceFileIndex[mb_strtolower($file)] ??= $item;
            }

            foreach (self::matchValues($item) as $value) {
                self::$matchValueIndex[mb_strtolower($value)] ??= $item;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private static function matchValues(array $item): array
    {
        return array_values(array_filter(array_merge([
            $item['name'] ?? '',
            $item['code'] ?? '',
            $item['displayName'] ?? '',
            ltrim((string) ($item['path'] ?? ''), '/'),
        ], $item['aliases'] ?? [], $item['legacyCodes'] ?? [])));
    }
}
