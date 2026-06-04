<x-app-layout>
    @section('title', '新增手机')

    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">新增手机</h1>
            <p class="mt-1 text-sm text-gray-500">录入一台手机的基础信息和完整参数。</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('products.store') }}" class="rounded-lg bg-white p-6 shadow-xs">
                @include('products._form')
            </form>
        </div>
    </div>
</x-app-layout>
