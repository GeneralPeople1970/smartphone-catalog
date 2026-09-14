<?php

namespace App\Services;

use App\Models\HomepageFeaturedPhone;
use App\Models\HomepageSlide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HomepageOrder
{
    public function prepend(string $model, array $attributes): Model
    {
        $this->validateModel($model);

        return DB::transaction(function () use ($model, $attributes) {
            $model::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $model::query()->increment('sort_order', 10);

            return $model::create([...$attributes, 'sort_order' => 0]);
        }, 3);
    }

    public function move(Model $item, int $direction): bool
    {
        $model = $item::class;
        $this->validateModel($model);
        if (! in_array($direction, [-1, 1], true)) {
            throw new InvalidArgumentException('Invalid move direction.');
        }

        return DB::transaction(function () use ($model, $item, $direction) {
            $rows = $model::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $index = $rows->search(fn (Model $row) => $row->is($item));
            $target = $index === false ? -1 : $index + $direction;
            if ($index === false || $target < 0 || $target >= $rows->count()) {
                return false;
            }
            $ids = $rows->modelKeys();
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            foreach ($ids as $position => $id) {
                $model::whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }

            return true;
        }, 3);
    }

    private function validateModel(string $model): void
    {
        if (! in_array($model, [HomepageSlide::class, HomepageFeaturedPhone::class], true)) {
            throw new InvalidArgumentException('Unsupported homepage model.');
        }
    }
}
