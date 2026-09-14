<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class PhoneQuery
{
    public static function order(Builder $query): Builder
    {
        return $query
            ->selectRaw('(CASE WHEN release_date IS NULL OR release_date = 0 THEN 1 ELSE 0 END) as date_missing')
            ->selectRaw('COALESCE(release_date, 0) as date_order')
            ->orderBy('date_missing')->orderByDesc('date_order')->orderBy('name')->orderBy('id');
    }

    public static function brand(Builder $query, ?string $brand): void
    {
        $entry = PhoneCatalog::entryForInput($brand);
        $names = $entry ? PhoneCatalog::brandInputValuesFor($entry) : [trim((string) $brand)];
        $column = $query->getConnection()->getDriverName() === 'mysql'
            ? 'BINARY LOWER(TRIM(brand))'
            : 'LOWER(TRIM(brand))';
        $query->whereIn($query->getConnection()->raw($column), array_values(array_unique(array_map(
            fn (string $name) => mb_strtolower(trim($name)), $names
        ))));
    }
}
