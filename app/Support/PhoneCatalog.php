<?php

namespace App\Support;

class PhoneCatalog
{
    /**
     * @return array<int, array{name: string, code: string, displayName: string, logo: ?string, path: string, sort: int, sourceFile: string, aliases?: array<int, string>, legacyCodes?: array<int, string>, sourceFiles?: array<int, string>}>
     */
    public static function brands(): array
    {
        return [
            ['name' => 'Apple', 'code' => 'APPLE', 'displayName' => '苹果', 'logo' => '/assets/brands/Apple.png', 'path' => '/APPLE', 'sort' => 1, 'sourceFile' => 'Apple.json', 'aliases' => ['苹果', 'iPhone', 'iPad'], 'sourceFiles' => ['Apple.json', 'Apple.js', '苹果.js', '苹果.json']],
            ['name' => 'Huawei', 'code' => 'HUAWEI', 'displayName' => '华为', 'logo' => '/assets/brands/Huawei.png', 'path' => '/HUAWEI', 'sort' => 2, 'sourceFile' => 'Huawei.json', 'aliases' => ['华为'], 'sourceFiles' => ['Huawei.json', 'Huawei.js', '华为.js', '华为.json']],
            ['name' => 'Xiaomi', 'code' => 'XIAOMI', 'displayName' => '小米', 'logo' => '/assets/brands/Xiaomi.png', 'path' => '/XIAOMI', 'sort' => 3, 'sourceFile' => 'Xiaomi.json', 'aliases' => ['小米', 'Mi'], 'sourceFiles' => ['Xiaomi.json', 'Xiaomi.js', '小米.js', 'xiaomi.js', '小米.json']],
            ['name' => 'Samsung', 'code' => 'SAMSUNG', 'displayName' => '三星', 'logo' => '/assets/brands/Samsung.png', 'path' => '/SAMSUNG', 'sort' => 4, 'sourceFile' => 'Samsung.json', 'aliases' => ['三星'], 'sourceFiles' => ['Samsung.json', 'Samsung.js', '三星.js']],
            ['name' => 'OPPO', 'code' => 'OPPO', 'displayName' => 'OPPO', 'logo' => '/assets/brands/OPPO.png', 'path' => '/OPPO', 'sort' => 5, 'sourceFile' => 'OPPO.json', 'aliases' => [], 'sourceFiles' => ['OPPO.json', 'OPPO.js', 'oppo.json']],
            ['name' => 'Meizu', 'code' => 'MEIZU', 'displayName' => '魅族', 'logo' => '/assets/brands/Meizu.png', 'path' => '/MEIZU', 'sort' => 6, 'sourceFile' => 'Meizu.json', 'aliases' => ['魅族'], 'sourceFiles' => ['Meizu.json', 'Meizu.js', '魅族.js']],
            ['name' => 'Realme', 'code' => 'REALME', 'displayName' => '真我', 'logo' => '/assets/brands/Realme.png', 'path' => '/REALME', 'sort' => 7, 'sourceFile' => 'Realme.json', 'aliases' => ['realme', '真我'], 'sourceFiles' => ['Realme.json', 'Realme.js', '真我.js']],
            ['name' => 'Honor', 'code' => 'HONOR', 'displayName' => '荣耀', 'logo' => '/assets/brands/HONOR.png', 'path' => '/HONOR', 'sort' => 8, 'sourceFile' => 'Honor.json', 'aliases' => ['荣耀'], 'sourceFiles' => ['Honor.json', 'Honor.js', '荣耀.js', '荣耀.json']],
            ['name' => 'Nubia', 'code' => 'NUBIA', 'displayName' => '努比亚', 'logo' => '/assets/brands/努比亚.png', 'path' => '/NUBIA', 'sort' => 9, 'sourceFile' => 'Nubia.json', 'aliases' => ['努比亚'], 'sourceFiles' => ['Nubia.json', 'Nubia.js', '努比亚.js']],
            ['name' => 'OnePlus', 'code' => 'ONEPLUS', 'displayName' => '一加', 'logo' => '/assets/brands/Oneplus.png', 'path' => '/ONEPLUS', 'sort' => 10, 'sourceFile' => 'OnePlus.json', 'aliases' => ['一加'], 'sourceFiles' => ['OnePlus.json', 'OnePlus.js', '一加.js']],
            ['name' => 'Vivo', 'code' => 'VIVO', 'displayName' => 'vivo', 'logo' => '/assets/brands/VIvo.png', 'path' => '/VIVO', 'sort' => 11, 'sourceFile' => 'Vivo.json', 'aliases' => ['vivo'], 'sourceFiles' => ['Vivo.json', 'Vivo.js', 'vivo.js', 'vivo.json']],
            ['name' => 'Lenovo', 'code' => 'LENOVO', 'displayName' => '联想', 'logo' => '/assets/brands/联想.png', 'path' => '/LENOVO', 'sort' => 12, 'sourceFile' => 'Lenovo.json', 'aliases' => ['联想', '联想小新', 'ZUK'], 'legacyCodes' => ['LENOVO_XIAOXIN', 'LIANXIANG'], 'sourceFiles' => ['Lenovo.json', 'Lenovo.js', '联想小新.js']],
            ['name' => 'Sony', 'code' => 'SONY', 'displayName' => '索尼', 'logo' => '/assets/brands/索尼.png', 'path' => '/SONY', 'sort' => 13, 'sourceFile' => 'Sony.json', 'aliases' => ['索尼'], 'sourceFiles' => ['Sony.json', 'Sony.js', '索尼.js']],
            ['name' => 'ZTE', 'code' => 'ZTE', 'displayName' => '中兴', 'logo' => '/assets/brands/中兴.png', 'path' => '/ZTE', 'sort' => 14, 'sourceFile' => 'ZTE.json', 'aliases' => ['中兴'], 'sourceFiles' => ['ZTE.json', 'ZTE.js', '中兴.js']],
            ['name' => 'ASUS', 'code' => 'ASUS', 'displayName' => '华硕', 'logo' => '/assets/brands/华硕.png', 'path' => '/ASUS', 'sort' => 15, 'sourceFile' => 'ASUS.json', 'aliases' => ['华硕'], 'sourceFiles' => ['ASUS.json', 'ASUS.js', '华硕.js']],
            ['name' => 'Google', 'code' => 'GOOGLE', 'displayName' => '谷歌', 'logo' => '/assets/brands/谷歌.png', 'path' => '/GOOGLE', 'sort' => 16, 'sourceFile' => 'Google.json', 'aliases' => ['谷歌', 'Pixel'], 'sourceFiles' => ['Google.json', 'Google.js', '谷歌.js']],
            ['name' => 'LG', 'code' => 'LG', 'displayName' => 'LG', 'logo' => '/assets/brands/LG.png', 'path' => '/LG', 'sort' => 17, 'sourceFile' => 'LG.json', 'aliases' => [], 'sourceFiles' => ['LG.json', 'LG.js']],
            ['name' => 'Nokia', 'code' => 'NOKIA', 'displayName' => '诺基亚', 'logo' => '/assets/brands/诺基亚.png', 'path' => '/NOKIA', 'sort' => 18, 'sourceFile' => 'Nokia.json', 'aliases' => ['诺基亚'], 'sourceFiles' => ['Nokia.json', 'Nokia.js', '诺基亚.js']],
            ['name' => 'Motorola', 'code' => 'MOTOROLA', 'displayName' => '摩托罗拉', 'logo' => '/assets/brands/摩托罗拉.png', 'path' => '/MOTOROLA', 'sort' => 19, 'sourceFile' => 'Motorola.json', 'aliases' => ['摩托罗拉', 'Moto'], 'sourceFiles' => ['Motorola.json', 'Motorola.js', '摩托罗拉.js']],
            ['name' => 'Redmi', 'code' => 'REDMI', 'displayName' => '红米', 'logo' => '/assets/brands/REDMI.png', 'path' => '/REDMI', 'sort' => 20, 'sourceFile' => 'Redmi.json', 'aliases' => ['红米'], 'sourceFiles' => ['Redmi.json', 'Redmi.js', '红米.js', '红米.json']],
        ];
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

    /**
     * @return array<int, string>
     */
    public static function resolveBrandNames(?string $brand): array
    {
        $brand = trim((string) $brand);

        if ($brand === '') {
            return [];
        }

        foreach (self::brands() as $item) {
            if (self::matches($item, $brand)) {
                return array_values(array_unique(array_merge([
                    $item['name'],
                    $item['displayName'],
                ], $item['aliases'] ?? [])));
            }
        }

        return [$brand];
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

        foreach (self::brands() as $item) {
            if (self::matches($item, $brand)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{name: string, code: string, displayName: string, logo: ?string, path: string, sort: int, aliases?: array<int, string>, sourceFiles?: array<int, string>}|null
     */
    public static function entryForProduct(string $brand, ?string $sourceFile): ?array
    {
        foreach (self::brands() as $item) {
            if ($sourceFile && in_array($sourceFile, $item['sourceFiles'] ?? [], true)) {
                return $item;
            }
        }

        return self::entryForInput($brand);
    }

    public static function codeForBrand(string $brand): string
    {
        foreach (self::brands() as $item) {
            if (self::matches($item, $brand)) {
                return $item['code'];
            }
        }

        return strtoupper($brand);
    }

    public static function canonicalBrandName(?string $brand, ?string $sourceFile = null): string
    {
        $entry = $sourceFile ? self::entryForProduct((string) $brand, $sourceFile) : self::entryForInput($brand);

        return $entry['name'] ?? trim((string) $brand);
    }

    public static function canonicalSourceFile(?string $brand, ?string $sourceFile = null): ?string
    {
        $entry = $sourceFile ? self::entryForProduct((string) $brand, $sourceFile) : self::entryForInput($brand);

        return $entry['sourceFile'] ?? $sourceFile;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function searchAliases(): array
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

    /**
     * @param  array<string, mixed>  $item
     */
    private static function matches(array $item, string $value): bool
    {
        $value = mb_strtolower(trim($value));

        return in_array($value, array_map(fn (string $item) => mb_strtolower($item), self::matchValues($item)), true);
    }
}
