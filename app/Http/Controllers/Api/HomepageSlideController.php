<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesApiFields;
use App\Http\Controllers\Controller;
use App\Models\HomepageSlide;
use App\Models\Product;
use App\Support\SafeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageSlideController extends Controller
{
    use ResolvesApiFields;

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
        $fields = $this->requestedFields($request, self::FIELDS, self::FIELD_ALIASES);

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
            'image' => Product::safeImageUrl($slide->image_path),
            'linkUrl' => SafeUrl::sanitize($slide->link_url),
            'sortOrder' => $slide->sort_order,
        ];

        return $this->onlyFields($values, $fields, null);
    }
}
