<x-app-layout>
    @section('title', '编辑手机')

    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">编辑手机</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $product->brand }} / {{ $product->name }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('products.update', $product) }}" class="rounded-lg bg-white p-6 shadow-xs">
                @method('PUT')
                @include('products._form')
            </form>
        </div>
    </div>
</x-app-layout>
