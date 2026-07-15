<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $hasActiveFilters = $request->filled('keyword')
            || $request->filled('role')
            || $request->filled('status');

        $users = User::query()
            ->when($request->filled('keyword'), function (Builder $query) use ($request) {
                $keyword = (string) $request->query('keyword');

                $query->where(function (Builder $inner) use ($keyword) {
                    $inner->where('name', 'like', '%'.$keyword.'%')
                        ->orWhere('email', 'like', '%'.$keyword.'%');
                });
            })
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role', (string) $request->query('role')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', (string) $request->query('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'hasActiveFilters' => $hasActiveFilters,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $newRole = UserRole::from($validated['role']);

        $this->authorize('updateRole', [$user, $newRole]);

        $oldRole = $user->role;

        if ($newRole !== $oldRole) {
            $user->role = $newRole;
            $user->save();

            Log::info('User role updated', [
                'actor_id' => $request->user()->id,
                'actor_email' => $request->user()->email,
                'target_id' => $user->id,
                'target_email' => $user->email,
                'old_role' => $oldRole->value,
                'new_role' => $newRole->value,
            ]);
        }

        return redirect()
            ->route('users.index')
            ->with('status', '已将 '.$user->email.' 的角色更新为「'.$newRole->label().'」。');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $newStatus = UserStatus::from($validated['status']);

        $this->authorize('updateStatus', [$user, $newStatus]);

        $oldStatus = $user->status;

        if ($newStatus !== $oldStatus) {
            $user->status = $newStatus;
            $user->save();

            Log::info('User status updated', [
                'actor_id' => $request->user()->id,
                'actor_email' => $request->user()->email,
                'target_id' => $user->id,
                'target_email' => $user->email,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ]);
        }

        return redirect()
            ->route('users.index')
            ->with('status', '已将 '.$user->email.' 的状态更新为「'.$newStatus->label().'」。');
    }
}
