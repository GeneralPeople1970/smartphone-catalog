<x-app-layout>
    @section('title', '批量导入手机')

    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">批量导入手机</h1>
            <p class="mt-1 text-sm text-gray-500">上传手机 JSON 数据，手机 ID 将使用 JSON 里的 id。</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <div class="font-medium">导入失败，数据库没有写入新数据。</div>
                    <div class="mt-2 whitespace-pre-line">{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="rounded-lg bg-white p-6 shadow-xs">
                @csrf

                <div>
                    <label for="files" class="block text-sm font-medium text-gray-700">JSON 文件</label>
                    <input
                        id="files"
                        name="files[]"
                        type="file"
                        accept=".json,application/json"
                        multiple
                        required
                        class="mt-1 block w-full rounded-md border border-gray-300 text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700"
                    >
                    <p class="mt-2 text-xs text-gray-500">可以一次选择多个 JSON 文件；每个文件根节点必须是数组，每条数据必须有唯一 id。</p>
                </div>

                <div class="mt-5">
                    <label for="status" class="block text-sm font-medium text-gray-700">导入状态</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="published" @selected(old('status', 'published') === 'published')>已发布</option>
                        <option value="draft" @selected(old('status') === 'draft')>草稿</option>
                    </select>
                </div>

                <div class="mt-6 rounded-md bg-gray-50 p-4 text-sm text-gray-600">
                    <div class="font-medium text-gray-900">导入规则</div>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li>数据库手机 ID 使用 JSON 字段 `id`。</li>
                        <li>如果 JSON 内部 ID 重复，或数据库已有相同手机 ID，会停止导入并提示。</li>
                        <li>图片、价格、处理器、电池和详细参数会按当前接口字段自动映射。</li>
                    </ul>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('products.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">返回列表</a>
                    <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        开始导入
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
