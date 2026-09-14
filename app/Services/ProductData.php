<?php

namespace App\Services;

use App\Models\Product;
use App\Support\PhoneCatalog;
use App\Support\PhoneFields;
use App\Support\SafeUrl;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

class ProductData
{
    public const MAX_DEPTH = 32;

    public const MAX_FIELD_LENGTH = 5000;

    private const TEXT_LIMITS = [
        'brand' => 191, 'name' => 191, 'slug' => 191,
        'image_url' => 2048, 'price' => 100, 'soc_name' => 191,
    ];

    public function fromForm(array $input, ?Product $product = null): array
    {
        $json = $input['specs_text'] ?? '';
        if (! is_string($json) && $json !== null) {
            throw ValidationException::withMessages(['specs_text' => '参数必须是 JSON 对象。']);
        }
        try {
            $decoded = $json === null || trim($json) === '' ? new stdClass : json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (! $decoded instanceof stdClass) {
                throw new JsonException;
            }
            // Keep nested objects as objects so an extension's {} does not
            // silently become [] when the record is saved or edited again.
            $specs = ! array_key_exists('specs_text', $input) && $product !== null
                ? $product->specsForEditing()
                : (array) $decoded;
            $this->validateSpecs($specs);
        } catch (JsonException|ValidationException $e) {
            $message = $e instanceof ValidationException ? implode(' ', Arr::flatten($e->errors())) : '参数必须是 JSON 对象。';
            throw ValidationException::withMessages(['specs_text' => $message]);
        }

        return $this->normalize($input, $specs, $product);
    }

    public function fromImport(array $item, string $filename, int $index, string $status): array
    {
        $this->validateSpecs($item);
        Validator::make($item, ['id' => ['required', $this->integerRule(1, PHP_INT_MAX)]])->validate();
        $sourceBrand = trim($item['company'] ?? '') ?: pathinfo($filename, PATHINFO_FILENAME);
        $brand = PhoneCatalog::importedBrandName($sourceBrand, $filename);
        $input = [
            'brand' => $brand,
            'name' => $item['phonename'] ?? $item['name'] ?? $brand.'-'.($index + 1),
            'image_url' => $item['imgurl'] ?? $item['image'] ?? null,
            'price' => $item['price'] ?? null,
            'soc_name' => $item['socname'] ?? $item['processor'] ?? null,
            'battery_capacity' => $this->battery($item['battery'] ?? null),
            'status' => $status,
        ];
        try {
            $data = $this->normalize($input, $item);
        } catch (ValidationException $exception) {
            $sourceFields = [
                'brand' => isset($item['company']) ? 'company' : 'brand',
                'name' => isset($item['phonename']) ? 'phonename' : 'name',
                'image_url' => isset($item['imgurl']) ? 'imgurl' : 'image',
                'soc_name' => isset($item['socname']) ? 'socname' : 'processor',
                'battery_capacity' => 'battery',
            ];
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$sourceFields[$field] ?? $field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        $data['id'] = (int) $item['id'];
        $data['source_key'] = sha1($filename.'|'.$data['id'].'|'.$data['name']);
        $data['source_file'] = $filename;
        $data['source_id'] = (string) $data['id'];
        if ($sourceBrand !== '' && $sourceBrand !== $brand) {
            $data['specs']['source_company'] ??= $sourceBrand;
        }

        return $data;
    }

    private function normalize(array $input, array $specs, ?Product $product = null): array
    {
        foreach (array_keys(self::TEXT_LIMITS) as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (is_string($input['brand'] ?? null)) {
            $input['brand'] = PhoneCatalog::canonicalBrandName($input['brand']);
        }
        if (is_string($input['slug'] ?? null) && mb_strlen($input['slug']) <= self::TEXT_LIMITS['slug']) {
            $input['slug'] = ProductWriter::normalizeSlug($input['slug']) ?: null;
        }
        $brands = array_column(PhoneCatalog::brands(), 'name');
        if ($product !== null) {
            $brands[] = trim($product->brand);
        }
        $data = Validator::make($input, [
            'brand' => ['required', 'string', 'max:'.self::TEXT_LIMITS['brand'], Rule::in($brands)],
            'name' => ['required', 'string', 'max:'.self::TEXT_LIMITS['name']],
            'slug' => ['nullable', 'string', 'max:'.self::TEXT_LIMITS['slug'], Rule::unique('products', 'slug')->ignore($product)],
            'image_url' => ['nullable', 'string', 'max:'.self::TEXT_LIMITS['image_url']],
            'price' => ['nullable', $this->textOrNumberRule(self::TEXT_LIMITS['price'])],
            'soc_name' => ['nullable', 'string', 'max:'.self::TEXT_LIMITS['soc_name']],
            'battery_capacity' => ['nullable', $this->integerRule(0, 30000)],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ])->validate();
        foreach (['image_url', 'price', 'soc_name'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }
        if (in_array($data['price'], ['0', '0.0', '0.00', '暂无', '暂无价格', '暂无报价', '待定'], true)) {
            $data['price'] = null;
        }
        $capacity = (int) ($data['battery_capacity'] ?? 0);
        $data['battery_capacity'] = $capacity > 0 ? $capacity : null;
        $data['specs'] = Product::syncSpecsWithFields($specs, ['id' => $product?->id, ...$data]);
        if (isset($data['specs']['official'])) {
            $data['specs']['official'] = SafeUrl::sanitize((string) $data['specs']['official']) ?? '';
        }

        return $data;
    }

    private function validateSpecs(array $specs): void
    {
        $rules = [];
        foreach (PhoneFields::SPECS as $field) {
            // Legacy measurement fields may contain text with units or numbers.
            $rules[$field] = ['nullable', $this->textOrNumberRule(self::MAX_FIELD_LENGTH)];
        }
        foreach (PhoneFields::EDIT_MAP as $field => $key) {
            if (! isset(self::TEXT_LIMITS[$field])) {
                continue;
            }
            $rules[$key] = $field === 'price'
                ? ['nullable', $this->textOrNumberRule(self::TEXT_LIMITS[$field])]
                : ['nullable', 'string', 'max:'.self::TEXT_LIMITS[$field]];
        }
        foreach (['name', 'image', 'processor'] as $alias) {
            $rules[$alias] = $rules[PhoneFields::ALIASES[$alias]];
        }
        $rules['official'] = ['nullable', 'string', 'max:2048'];
        $rules['id'] = ['nullable', $this->integerRule(1, PHP_INT_MAX)];
        $rules['saledate'] = ['nullable', $this->integerRule(0, 99991231)];
        $rules['battery'] = ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
            try {
                $this->battery($value);
            } catch (ValidationException $exception) {
                $fail(implode(' ', Arr::flatten($exception->errors())));
            }
        }];
        Validator::make($specs, $rules)->validate();
        $this->validateExtensions($specs);
    }

    private function validateExtensions(array|stdClass $values, string $prefix = ''): void
    {
        foreach ($values as $key => $value) {
            $path = $prefix.$key;
            if (is_string($value) && mb_strlen($value) > self::MAX_FIELD_LENGTH) {
                throw ValidationException::withMessages([$path => $path.' 字段过长。']);
            }
            if (is_float($value) && ! is_finite($value)) {
                throw ValidationException::withMessages([$path => $path.' 数字超出可保存范围。']);
            }
            if (is_array($value) || $value instanceof stdClass) {
                $this->validateExtensions($value, $path.'.');
            }
        }
    }

    private function battery(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^(\d+)(?:\s*mAh)?$/iD', trim((string) $value), $match)) {
            throw ValidationException::withMessages(['battery' => 'battery 必须是容量数字，可带 mAh。']);
        }
        if (! $this->isIntegerInRange($match[1], 0, 30000)) {
            throw ValidationException::withMessages(['battery' => 'battery_capacity 不能超过 30000。']);
        }

        return (int) $match[1] ?: null;
    }

    private function textOrNumberRule(int $maxLength): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($maxLength): void {
            if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || (is_float($value) && ! is_finite($value))) {
                $fail($attribute.' 必须是文本或有限数字。');
            } elseif (mb_strlen((string) $value) > $maxLength) {
                $fail($attribute.' 不能超过 '.$maxLength.' 个字符。');
            }
        };
    }

    private function integerRule(int $minimum, int $maximum): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minimum, $maximum): void {
            if (! $this->isIntegerInRange($value, $minimum, $maximum)) {
                $fail($attribute.' 必须是 '.$minimum.' 至 '.$maximum.' 范围内的整数。');
            }
        };
    }

    private function isIntegerInRange(mixed $value, int $minimum, int $maximum): bool
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d+$/D', trim((string) $value))) {
            return false;
        }

        $digits = ltrim(trim((string) $value), '0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = (string) $maximum;

        return (strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0))
            && (int) $digits >= $minimum;
    }
}
