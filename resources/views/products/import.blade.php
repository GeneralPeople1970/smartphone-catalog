<x-app-layout>
    @section('title', '批量导入手机')

    <x-slot name="header">
        <div class="admin-form-shell-narrow">
            <h1 class="admin-page-title">批量导入手机</h1>
            <p class="admin-page-subtitle">上传 JSON 数据，导入时会统一品牌、基础字段和发布状态。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container">
            <div class="admin-form-shell-narrow space-y-6">
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
                            <input id="files" name="files[]" type="file" accept=".json,application/json" multiple required class="admin-file-input">
                            <p class="admin-hint">可一次选择多个文件，全部校验通过后才会写入。</p>
                        </div>

                        <div class="admin-field admin-field-narrow">
                            <label for="status">导入状态</label>
                            <select id="status" name="status" class="admin-select">
                                <option value="published" @selected(old('status', 'published') === 'published')>已发布</option>
                                <option value="draft" @selected(old('status') === 'draft')>草稿</option>
                            </select>
                        </div>

                        <div class="admin-note">
                            <div class="admin-note-title">导入规则</div>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>数据库手机 ID 使用 JSON 字段 <code>id</code>。</li>
                                <li>重复 ID、重复来源或无效 JSON 会停止整批导入。</li>
                                <li>品牌会按统一目录归一，slug 会自动生成。</li>
                            </ul>
                        </div>

                        <div class="admin-form-actions admin-form-actions-end">
                            <a href="{{ route('products.index') }}" class="admin-button">返回列表</a>
                            <button type="submit" class="admin-button-primary">开始导入</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
