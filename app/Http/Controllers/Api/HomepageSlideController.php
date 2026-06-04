<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageSlideController extends Controller
{
    private const FIELDS = [
        'id',
        'title',
        'image',
        'linkUrl',
        'sortOrder',
    ];

    private const FIELD_ALIASES = [
        'image_path' => 'image',
        'imagePath' => 'image',
        'imgurl' => 'image',
        'url' => 'image',
        'link_url' => 'linkUrl',
        'link' => 'linkUrl',
        'sort' => 'sortOrder',
        'sort_order' => 'sortOrder',
    ];

    public function index(Request $request): JsonResponse
    {
        $fields = $this->requestedFields($request);

        $slides = HomepageSlide::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomepageSlide $slide) => $this->toItem($slide, $fields))
            ->values();

        return response()->json($slides);
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function toItem(HomepageSlide $slide, array $fields): array
    {
        $values = [
            'id' => $slide->id,
            'title' => $slide->title,
            'image' => $slide->image_path,
            'linkUrl' => $slide->link_url,
            'sortOrder' => $slide->sort_order,
        ];

        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $values[$field]])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function requestedFields(Request $request): array
    {
        $fields = $this->parseList($request->query('fields'));

        if ($fields === []) {
            return self::FIELDS;
        }

        $fields = collect($fields)
            ->map(fn (string $field) => self::FIELD_ALIASES[$field] ?? $field)
            ->unique()
            ->values()
            ->all();
        $invalid = array_values(array_diff($fields, self::FIELDS));

        if ($invalid !== []) {
            abort(response()->json([
                'message' => '不支持的字段。',
                'invalidFields' => $invalid,
                'allowedFields' => self::FIELDS,
            ], 422));
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    private function parseList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->flatMap(fn ($item) => explode(',', (string) $item))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
