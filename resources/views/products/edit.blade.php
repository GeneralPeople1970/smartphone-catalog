<x-app-layout>
    @section('title', '编辑手机')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">编辑手机</h1>
            <p class="admin-page-subtitle">{{ $product->brand }} / {{ $product->name }}</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container">
            <form method="POST" action="{{ route('products.update', $product) }}" class="admin-panel admin-panel-body">
                @method('PUT')
                @include('products._form')
            </form>
        </div>
    </div>
</x-app-layout>
