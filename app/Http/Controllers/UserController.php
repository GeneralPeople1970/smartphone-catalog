<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\LastActiveOwnerException;
use App\Models\User;
use App\Services\OwnerGuard;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        try {
            $oldRole = OwnerGuard::mutate($user, function (User $locked, User $actor) use ($newRole): UserRole {
                Gate::forUser($actor)->authorize('updateRole', [$locked, $newRole]);
                $oldRole = $locked->role;

                if ($newRole !== $oldRole) {
                    $locked->role = $newRole;
                    $locked->save();
                }

                return $oldRole;
            }, $request->user());
        } catch (LastActiveOwnerException $e) {
            return redirect()
                ->route('users.index')
                ->with('error', $e->getMessage());
        }

        if ($newRole !== $oldRole) {
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

        try {
            $oldStatus = OwnerGuard::mutate($user, function (User $locked, User $actor) use ($newStatus): UserStatus {
                Gate::forUser($actor)->authorize('updateStatus', [$locked, $newStatus]);
                $oldStatus = $locked->status;

                if ($newStatus !== $oldStatus) {
                    $locked->status = $newStatus;
                    $locked->save();
                }

                return $oldStatus;
            }, $request->user());
        } catch (LastActiveOwnerException $e) {
            return redirect()
                ->route('users.index')
                ->with('error', $e->getMessage());
        }

        if ($newStatus !== $oldStatus) {
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

    /**
     * Mark a user's email address as verified, or take that back.
     *
     * The escape hatch for the registration verification switch: an address that
     * can no longer receive mail, or an account that registered before the
     * switch was turned on, is released from here. Taking verification back ends
     * that account's session on its next request while the switch is on.
     */
    public function updateEmailVerification(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        $this->authorize('updateEmailVerification', $user);

        $verified = (bool) $validated['verified'];

        $changed = OwnerGuard::mutate($user, function (User $locked, User $actor) use ($verified): bool {
            Gate::forUser($actor)->authorize('updateEmailVerification', $locked);

            if ($verified === $locked->hasVerifiedEmail()) {
                return false;
            }

            if ($verified) {
                $locked->markEmailAsVerified();
            } else {
                $locked->forceFill(['email_verified_at' => null])->save();
            }

            return true;
        }, $request->user());

        if ($changed) {
            if ($verified) {
                event(new Verified($user));
            }

            Log::info('User email verification updated', [
                'actor_id' => $request->user()->id,
                'actor_email' => $request->user()->email,
                'target_id' => $user->id,
                'target_email' => $user->email,
                'verified' => $verified,
            ]);
        }

        return redirect()
            ->route('users.index')
            ->with('status', '已将 '.$user->email.' 标记为「'.($verified ? '邮箱已验证' : '邮箱未验证').'」。');
    }
}
