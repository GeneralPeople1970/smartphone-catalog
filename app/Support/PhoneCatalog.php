<?php

namespace App\Support;

class PhoneCatalog
{
    /**
     * @return array<int, array{name: string, code: string, displayName: string, logo: ?string, path: string, sort: int, aliases?: array<int, string>}>
     */
    public static function brands(): array
    {
        return [
            ['name' => '苹果', 'code' => 'APPLE', 'displayName' => '苹果', 'logo' => '/assets/brands/Apple.png', 'path' => '/APPLE', 'sort' => 1, 'aliases' => ['Apple', 'iPhone', 'iPad'], 'sourceFiles' => ['苹果.js', '苹果.json']],
            ['name' => '华为', 'code' => 'HUAWEI', 'displayName' => '华为', 'logo' => '/assets/brands/Huawei.png', 'path' => '/HUAWEI', 'sort' => 2, 'aliases' => ['Huawei'], 'sourceFiles' => ['华为.js', '华为.json']],
            ['name' => '小米', 'code' => 'XIAOMI', 'displayName' => '小米', 'logo' => '/assets/brands/Xiaomi.png', 'path' => '/XIAOMI', 'sort' => 3, 'aliases' => ['Xiaomi', 'Mi'], 'sourceFiles' => ['小米.js', 'xiaomi.js', '小米.json']],
            ['name' => '三星', 'code' => 'SAMSUNG', 'displayName' => '三星', 'logo' => '/assets/brands/Samsung.png', 'path' => '/SAMSUNG', 'sort' => 4, 'sourceFiles' => ['三星.js']],
            ['name' => 'OPPO', 'code' => 'OPPO', 'displayName' => 'OPPO', 'logo' => '/assets/brands/OPPO.png', 'path' => '/OPPO', 'sort' => 5, 'sourceFiles' => ['OPPO.js', 'oppo.json']],
            ['name' => '魅族', 'code' => 'MEIZU', 'displayName' => '魅族', 'logo' => '/assets/brands/Meizu.png', 'path' => '/MEIZU', 'sort' => 6, 'sourceFiles' => ['魅族.js']],
            ['name' => 'realme', 'code' => 'REALME', 'displayName' => 'Realme', 'logo' => '/assets/brands/Realme.png', 'path' => '/REALME', 'sort' => 7, 'aliases' => ['真我', 'Realme'], 'sourceFiles' => ['真我.js']],
            ['name' => '荣耀', 'code' => 'HONOR', 'displayName' => '荣耀', 'logo' => '/assets/brands/HONOR.png', 'path' => '/HONOR', 'sort' => 8, 'aliases' => ['Honor'], 'sourceFiles' => ['荣耀.js', '荣耀.json']],
            ['name' => '努比亚', 'code' => 'NUBIA', 'displayName' => '努比亚', 'logo' => '/assets/brands/努比亚.png', 'path' => '/NUBIA', 'sort' => 9, 'sourceFiles' => ['努比亚.js']],
            ['name' => '一加', 'code' => 'ONEPLUS', 'displayName' => '一加', 'logo' => '/assets/brands/Oneplus.png', 'path' => '/ONEPLUS', 'sort' => 10, 'sourceFiles' => ['一加.js']],
            ['name' => 'vivo', 'code' => 'VIVO', 'displayName' => 'vivo', 'logo' => '/assets/brands/VIvo.png', 'path' => '/VIVO', 'sort' => 11, 'sourceFiles' => ['vivo.js', 'vivo.json']],
            ['name' => '联想小新', 'code' => 'LENOVO_XIAOXIN', 'displayName' => '联想小新', 'logo' => '/assets/brands/联想.png', 'path' => '/LENOVO_XIAOXIN', 'sort' => 12, 'aliases' => ['联想'], 'sourceFiles' => ['联想小新.js']],
            ['name' => '索尼', 'code' => 'SONY', 'displayName' => '索尼', 'logo' => '/assets/brands/索尼.png', 'path' => '/SONY', 'sort' => 13, 'sourceFiles' => ['索尼.js']],
            ['name' => '中兴', 'code' => 'ZTE', 'displayName' => '中兴', 'logo' => '/assets/brands/中兴.png', 'path' => '/ZTE', 'sort' => 14, 'sourceFiles' => ['中兴.js']],
            ['name' => '华硕', 'code' => 'ASUS', 'displayName' => '华硕', 'logo' => '/assets/brands/华硕.png', 'path' => '/ASUS', 'sort' => 15, 'sourceFiles' => ['华硕.js']],
            ['name' => '谷歌', 'code' => 'GOOGLE', 'displayName' => '谷歌', 'logo' => '/assets/brands/谷歌.png', 'path' => '/GOOGLE', 'sort' => 16, 'sourceFiles' => ['谷歌.js']],
            ['name' => 'LG', 'code' => 'LG', 'displayName' => 'LG', 'logo' => '/assets/brands/LG.png', 'path' => '/LG', 'sort' => 17, 'sourceFiles' => ['LG.js']],
            ['name' => '诺基亚', 'code' => 'NOKIA', 'displayName' => '诺基亚', 'logo' => '/assets/brands/诺基亚.png', 'path' => '/NOKIA', 'sort' => 18, 'sourceFiles' => ['诺基亚.js']],
            ['name' => '摩托罗拉', 'code' => 'MOTOROLA', 'displayName' => '摩托罗拉', 'logo' => '/assets/brands/摩托罗拉.png', 'path' => '/MOTOROLA', 'sort' => 19, 'sourceFiles' => ['摩托罗拉.js']],
            ['name' => '红米', 'code' => 'REDMI', 'displayName' => '红米', 'logo' => '/assets/brands/REDMI.png', 'path' => '/REDMI', 'sort' => 20, 'aliases' => ['Redmi'], 'sourceFiles' => ['红米.js', '红米.json']],
        ];
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
            $matches = array_map('strtolower', array_merge([
                $item['name'],
                $item['code'],
                $item['displayName'],
            ], $item['aliases'] ?? []));

            if (in_array(strtolower($brand), $matches, true)) {
                return array_values(array_unique(array_merge([$item['name']], $item['aliases'] ?? [])));
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
            $matches = array_map('strtolower', array_merge([
                $item['name'],
                $item['code'],
                $item['displayName'],
            ], $item['aliases'] ?? []));

            if (in_array(strtolower($brand), $matches, true)) {
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
            if (in_array($brand, array_merge([$item['name'], $item['displayName']], $item['aliases'] ?? []), true)) {
                return $item['code'];
            }
        }

        return strtoupper($brand);
    }
}
