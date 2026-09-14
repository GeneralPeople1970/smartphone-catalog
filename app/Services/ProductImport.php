<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

class ProductImport
{
    public const MAX_FILES = 20;

    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_RECORDS = 2000;

    public const MAX_FILENAME_LENGTH = 191;

    public function __construct(private ProductData $data, private ProductWriter $writer) {}

    public function import(array $files, string $status): int
    {
        if (collect($files)->sum(fn ($file) => $file->getSize()) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['files' => '上传文件总大小超过 10MB 限制。']);
        }
        $records = [];
        $errors = [];
        $processed = 0;
        foreach ($files as $file) {
            $filename = $file->getClientOriginalName();
            if (mb_strlen($filename) > self::MAX_FILENAME_LENGTH) {
                $errors[] = $filename.' source_file（该文件全部记录）: 文件名不能超过 '.self::MAX_FILENAME_LENGTH.' 个字符。';

                continue;
            }
            try {
                if (strtolower($file->getClientOriginalExtension()) !== 'json') {
                    throw new JsonException;
                }
                $items = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $file->getContent()), false, ProductData::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                if (! is_array($items)) {
                    throw new JsonException;
                }
            } catch (JsonException $e) {
                $errors[] = $filename.' 根节点必须是 JSON 对象数组。';

                continue;
            }
            if (count($items) + $processed > self::MAX_RECORDS) {
                $errors[] = $filename.' 导入记录总数超过上限 '.self::MAX_RECORDS.' 条。';
                break;
            }
            foreach ($items as $index => $item) {
                $processed++;
                $location = $filename.' 第 '.($index + 1).' 条';
                if (! $item instanceof stdClass) {
                    $errors[] = $location.'必须是对象。';

                    continue;
                }
                try {
                    $record = $this->data->fromImport((array) $item, $filename, $index, $status);
                    $id = $record['id'];
                    if (isset($records[$id])) {
                        $errors[] = $location.' id '.$id.' 重复。';
                    } else {
                        $records[$id] = ['data' => $record, 'location' => $location];
                    }
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $errors[] = $location.' '.$field.': '.implode(' ', $messages);
                    }
                }
            }
        }
        $existingIds = Product::whereIn('id', array_keys($records))->pluck('id')->all();
        $sourceKeys = array_map(fn ($record) => $record['data']['source_key'], $records);
        $existingSources = Product::whereIn('source_key', $sourceKeys)->pluck('source_key')->all();
        foreach ($records as $id => $record) {
            if (in_array($id, $existingIds)) {
                $errors[] = $record['location'].' id: '.$id.' 已存在。';
            }
            if (in_array($record['data']['source_key'], $existingSources, true)) {
                $errors[] = $record['location'].' source_key: 来源已存在。';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['files' => implode("\n", $errors)]);
        }
        DB::transaction(function () use ($records) {
            foreach ($records as $record) {
                try {
                    $this->writer->save($record['data']);
                } catch (ValidationException $e) {
                    $messages = [];
                    foreach ($e->errors() as $field => $errors) {
                        $messages[] = $record['location'].' '.$field.': '.implode(' ', $errors);
                    }
                    throw ValidationException::withMessages(['files' => implode("\n", $messages)]);
                }
            }
        });

        return count($records);
    }
}
