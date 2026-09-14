@props(['errorTitle' => '提交失败，请检查下面的问题。'])
@if (session('status'))
    <div class="admin-alert-success" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="admin-alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="admin-alert-danger" role="alert">
        <div class="font-bold">{{ $errorTitle }}</div>
        <ul class="mt-2 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li class="whitespace-pre-line">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
