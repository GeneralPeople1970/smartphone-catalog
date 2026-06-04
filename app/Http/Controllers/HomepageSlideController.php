<?php

namespace App\Http\Controllers;

use App\Models\HomepageSlide;
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
    public function index(): View
    {
        return view('homepage-slides.index', [
            'slides' => HomepageSlide::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'link_url' => ['nullable', 'string', 'max:2048'],
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
            $this->deleteLocalImage($imagePath);

            throw $exception;
        }

        return redirect()
            ->route('homepage-slides.index')
            ->with('status', '轮播图已上传。');
    }

    public function update(Request $request, HomepageSlide $homepageSlide): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'link_url' => ['nullable', 'string', 'max:2048'],
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
        $imagePath = $homepageSlide->image_path;
        $homepageSlide->delete();
        $this->deleteManagedImage($imagePath);

        return redirect()
            ->route('homepage-slides.index')
            ->with('status', '轮播图已删除。');
    }

    public function moveUp(HomepageSlide $homepageSlide): RedirectResponse
    {
        return $this->move($homepageSlide, -1);
    }

    public function moveDown(HomepageSlide $homepageSlide): RedirectResponse
    {
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

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $basename = Str::of(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->slug('-');

        if ($basename->isEmpty()) {
            $basename = Str::of('slide');
        }

        $filename = now()->format('YmdHis').'-'.$basename.'-'.Str::random(6).'.'.$extension;
        $path = 'homepage/'.$filename;

        try {
            $stored = Storage::disk('public')->putFileAs('homepage', $file, $filename);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'image' => '图片保存失败，请确认 storage/app/public/homepage 目录可写。',
            ]);
        }

        if ($stored !== $path) {
            throw ValidationException::withMessages([
                'image' => '图片保存失败，请重新选择图片后再试。',
            ]);
        }

        return '/storage/'.$path;
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
