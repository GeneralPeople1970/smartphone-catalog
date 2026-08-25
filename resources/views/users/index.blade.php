<x-app-layout>
    @section('title', '用户管理')

    <x-slot name="header">
        <div class="admin-toolbar">
            <div>
                <h1 class="admin-page-title">用户管理</h1>
                <p class="admin-page-subtitle">查看账号、调整角色、停用或恢复用户。所有操作都会记录操作日志。</p>
            </div>
        </div>
    </x-slot>

    <div class="admin-page">
        <div class="admin-container space-y-6">
            @if (session('status'))
                <div class="admin-alert-success">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="admin-alert-danger">{{ session('error') }}</div>
            @endif

            @error('role')
                <div class="admin-alert-danger">{{ $message }}</div>
            @enderror
            @error('status')
                <div class="admin-alert-danger">{{ $message }}</div>
            @enderror

            <section class="admin-panel">
                <form method="GET" action="{{ route('users.index') }}" class="admin-filter-bar">
                    <div class="admin-field admin-field-keyword">
                        <label for="keyword">关键词</label>
                        <input id="keyword" type="text" name="keyword" value="{{ request('keyword') }}" placeholder="用户名或邮箱" class="admin-input">
                    </div>
                    <div class="admin-field">
                        <label for="role">角色</label>
                        <select id="role" name="role" class="admin-select">
                            <option value="">全部角色</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-field">
                        <label for="status">状态</label>
                        <select id="status" name="status" class="admin-select">
                            <option value="">全部状态</option>
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-filter-actions">
                        <button type="submit" class="admin-button-primary">筛选</button>
                        @if ($hasActiveFilters)
                            <a href="{{ route('users.index') }}" class="admin-button">重置</a>
                        @endif
                    </div>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户</th>
                                <th>邮箱</th>
                                <th>角色</th>
                                <th>状态</th>
                                <th>注册时间</th>
                                <th class="text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                @php
                                    $isSelf = auth()->id() === $user->id;
                                    $assignableRoles = collect($roles)->filter(
                                        fn ($role) => $role === $user->role || auth()->user()->can('updateRole', [$user, $role])
                                    );
                                    $canChangeRole = $assignableRoles->contains(fn ($role) => $role !== $user->role);
                                    $canSuspend = auth()->user()->can('updateStatus', [$user, \App\Enums\UserStatus::Suspended]);
                                    $canRestore = auth()->user()->can('updateStatus', [$user, \App\Enums\UserStatus::Active]);
                                @endphp
                                <tr>
                                    <td class="admin-text-strong">#{{ $user->id }}</td>
                                    <td>
                                        <div class="admin-text-strong">
                                            {{ $user->name }}
                                            @if ($isSelf)
                                                <span class="admin-text-muted text-xs font-normal">（本人）</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="admin-text-muted">{{ $user->email }}</td>
                                    <td>
                                        @if ($canChangeRole)
                                            <form method="POST" action="{{ route('users.role', $user) }}" class="admin-inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <select name="role" class="admin-select admin-select-compact">
                                                    @foreach ($assignableRoles as $role)
                                                        <option value="{{ $role->value }}" @selected($role === $user->role)>{{ $role->label() }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="admin-button">保存</button>
                                            </form>
                                        @else
                                            <span class="status-pill status-pill-muted">{{ $user->role->label() }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $user->isSuspended() ? 'status-pill-muted' : 'status-pill-active' }}">
                                            {{ $user->status->label() }}
                                        </span>
                                    </td>
                                    <td class="admin-text-muted">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="admin-table-actions">
                                            @if ($user->isSuspended())
                                                @if ($canRestore)
                                                    <form method="POST" action="{{ route('users.status', $user) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ \App\Enums\UserStatus::Active->value }}">
                                                        <button type="submit" class="admin-button">恢复</button>
                                                    </form>
                                                @endif
                                            @else
                                                @if ($canSuspend)
                                                    <form method="POST" action="{{ route('users.status', $user) }}" onsubmit="return confirm('确认停用该账号吗？该用户将无法登录。');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ \App\Enums\UserStatus::Suspended->value }}">
                                                        <button type="submit" class="admin-button-danger">停用</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="admin-empty">
                                        @if ($hasActiveFilters)
                                            没有找到符合条件的用户，请调整筛选条件。
                                        @else
                                            暂无用户数据。
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="admin-panel-footer">
                        {{ $users->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
