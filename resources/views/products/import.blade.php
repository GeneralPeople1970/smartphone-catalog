<x-app-layout>
    @section('title', '批量导入手机')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">批量导入手机</h1>
            <p class="admin-page-subtitle">上传 JSON 数据，导入时会统一品牌、基础字段和发布状态。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container max-w-3xl space-y-6">
            @if ($errors->any())
                <div class="admin-alert-danger">
                    <div class="font-bold">导入失败，数据库没有写入新数据。</div>
                    <div class="mt-2 whitespace-pre-line">{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="admin-panel">
                @csrf

                <div class="admin-panel-body space-y-5">
                    <div class="admin-field">
                        <label for="files">JSON 文件</label>
                        <input
                            id="files"
                            name="files[]"
                            type="file"
                            accept=".json,application/json"
                            multiple
                            required
                            class="admin-input file:mr-4 file:h-full file:border-0 file:bg-gray-900 file:px-4 file:text-sm file:font-semibold file:text-white"
                        >
                    </div>

                    <div class="admin-field">
                        <label for="status">导入状态</label>
                        <select id="status" name="status" class="admin-select">
                            <option value="published" @selected(old('status', 'published') === 'published')>已发布</option>
                            <option value="draft" @selected(old('status') === 'draft')>草稿</option>
                        </select>
                    </div>

                    <div class="rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                        <div class="font-bold text-gray-900">导入规则</div>
                        <ul class="mt-2 list-inside list-disc space-y-1">
                            <li>数据库手机 ID 使用 JSON 字段 <code>id</code>。</li>
                            <li>重复 ID、重复来源或无效 JSON 会停止整批导入。</li>
                            <li>品牌会按统一目录归一，slug 会自动生成。</li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <a href="{{ route('products.index') }}" class="admin-button">返回列表</a>
                        <button type="submit" class="admin-button-primary">开始导入</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
