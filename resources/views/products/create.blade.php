<x-app-layout>
    @section('title', '新增手机')

    <x-slot name="header">
        <div>
            <h1 class="admin-page-title">新增手机</h1>
            <p class="admin-page-subtitle">录入一台手机的基础信息和完整参数。</p>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container">
            <form method="POST" action="{{ route('products.store') }}" class="admin-panel admin-panel-body">
                @include('products._form')
            </form>
        </div>
    </div>
</x-app-layout>
