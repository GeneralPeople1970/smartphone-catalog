<?php

namespace App\Http\Controllers;

use App\Models\HomepageSlide;
use App\Rules\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HomepageSlideController extends Controller
{
    /**
     * Server-detected MIME type => safe file extension. The stored extension is
     * derived from this map, never from the client-supplied file name.
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** Maximum accepted width/height (px) and total pixel count. */
    private const MAX_DIMENSION = 4000;

    private const MAX_PIXELS = 10_000_000;

    public function index(): View
    {
        $this->authorize('viewAny', HomepageSlide::class);

        return view('homepage-slides.index', [
            'slides' => HomepageSlide::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HomepageSlide::class);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'link_url' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'is_active' => ['nullable', Rule::in(['1'])],
        ], $this->validationMessages(), $this->validationAttributes());

        $imagePath = $this->storeImage($request);

        try {
            DB::transaction(function () use ($validated, $request, $imagePath) {
                HomepageSlide::query()->increment('sort_order', 10);

                HomepageSlide::create([
                    'title' => $validated['title'] ?? null,
                    'image_path' => $imagePath,
                    'link_url' => $validated['link_url'] ?? null,
                    'sort_order' => 0,
                    'is_active' => $request->boolean('is_active', true),
                ]);
            });
        } catch (\Throwable $exception) {
            $this->deleteManagedImage($imagePath);

            throw $exception;
        }

        return redirect()
            ->route('homepage-slides.index')
            ->with('status', '轮播图已上传。');
    }

    public function update(Request $request, HomepageSlide $homepageSlide): RedirectResponse
    {
        $this->authorize('update', $homepageSlide);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'link_url' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'is_active' => ['nullable', Rule::in(['1'])],
        ], $this->validationMessages(), $this->validationAttributes());

        $data = [
            'title' => $validated['title'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('image')) {
            $oldImagePath = $homepageSlide->image_path;
            $newImagePath = $this->storeImage($request);
            $data['image_path'] = $newImagePath;

            try {
                $homepageSlide->update($data);
            } catch (\Throwable $exception) {
                $this->deleteManagedImage($newImagePath);

                throw $exception;
            }

            $this->deleteManagedImage($oldImagePath);

            return redirect()
                ->route('homepage-slides.index')
                ->with('status', '轮播图已更新。');
        }

        $homepageSlide->update($data);

        return redirect()
            ->route('homepage-slides.index')
            ->with('status', '轮播图已更新。');
    }

    public function destroy(HomepageSlide $homepageSlide): RedirectResponse
    {
        $this->authorize('delete', $homepageSlide);

        $imagePath = $homepageSlide->image_path;
        $homepageSlide->delete();
        $this->deleteManagedImage($imagePath);

        return redirect()
            ->route('homepage-slides.index')
            ->with('status', '轮播图已删除。');
    }

    public function moveUp(HomepageSlide $homepageSlide): RedirectResponse
    {
        $this->authorize('update', $homepageSlide);

        return $this->move($homepageSlide, -1);
    }

    public function moveDown(HomepageSlide $homepageSlide): RedirectResponse
    {
        $this->authorize('update', $homepageSlide);

        return $this->move($homepageSlide, 1);
    }

    private function move(HomepageSlide $homepageSlide, int $direction): RedirectResponse
    {
        $slides = HomepageSlide::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $slides->search(fn (HomepageSlide $slide) => $slide->is($homepageSlide));
        $targetIndex = $index === false ? -1 : $index + $direction;

        if ($index === false || $targetIndex < 0 || $targetIndex >= $slides->count()) {
            return redirect()
                ->route('homepage-slides.index')
                ->with('status', $direction < 0 ? '这张轮播图已经在最前面。' : '这张轮播图已经在最后面。');
        }

        $ids = $slides->pluck('id')->all();
        [$ids[$index], $ids[$targetIndex]] = [$ids[$targetIndex], $ids[$index]];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                HomepageSlide::whereKey($id)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        return redirect()
            ->route('homepage-slides.index')
            ->with('status', '轮播图顺序已更新。');
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('image');

        if (! $file || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'image' => '图片上传失败，请重新选择图片后再试。',
            ]);
        }

        // Trust the server-detected MIME type, not the client file name/extension.
        $extension = self::MIME_EXTENSIONS[(string) $file->getMimeType()] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'image' => '图片格式仅支持 jpg、jpeg、png、webp、gif。',
            ]);
        }

        // Re-decode and re-encode through GD: this proves the bytes are a real
        // image, strips metadata/EXIF and any appended polyglot/script payload,
        // and normalizes the output to a single safe format.
        $encoded = $this->reencodeImage($file->getRealPath(), $extension);

        // Unpredictable random name; the original file name is never reused.
        $path = 'homepage/'.Str::random(40).'.'.$extension;

        if (Storage::disk('public')->put($path, $encoded) === false) {
            throw ValidationException::withMessages([
                'image' => '图片保存失败，请确认 storage/app/public/homepage 目录可写。',
            ]);
        }

        return '/storage/'.$path;
    }

    /**
     * Validate the dimensions/pixel-count and re-encode the image through GD,
     * returning the sanitized binary contents.
     */
    private function reencodeImage(string $sourcePath, string $extension): string
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            throw ValidationException::withMessages([
                'image' => '无法识别的图片文件。',
            ]);
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1
            || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
            || ($width * $height) > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                'image' => '图片尺寸过大，单边不超过 '.self::MAX_DIMENSION.' 像素，且总像素不超过 '.self::MAX_PIXELS.'。',
            ]);
        }

        $image = @imagecreatefromstring((string) file_get_contents($sourcePath));

        if ($image === false) {
            throw ValidationException::withMessages([
                'image' => '无法解码图片文件。',
            ]);
        }

        if ($extension === 'png' || $extension === 'webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();

        match ($extension) {
            'jpg' => imagejpeg($image, null, 85),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 85),
            'gif' => imagegif($image),
        };

        $encoded = (string) ob_get_clean();

        if ($encoded === '') {
            throw ValidationException::withMessages([
                'image' => '图片处理失败，请重新选择图片后再试。',
            ]);
        }

        return $encoded;
    }

    private function deleteManagedImage(?string $imagePath): void
    {
        if (! $imagePath || ! Str::startsWith($imagePath, '/storage/homepage/')) {
            return;
        }

        Storage::disk('public')->delete(Str::after($imagePath, '/storage/'));
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'title' => '标题',
            'image' => '图片',
            'link_url' => '跳转链接',
            'sort_order' => '排序',
            'is_active' => '上架状态',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'image.required' => '请选择要上传的图片。',
            'image.file' => '上传内容必须是图片文件。',
            'image.mimes' => '图片格式仅支持 jpg、jpeg、png、webp、gif。',
            'image.max' => '图片不能超过 20MB。',
            'image.uploaded' => '图片上传失败，请检查文件大小或重新选择图片。',
        ];
    }
}
