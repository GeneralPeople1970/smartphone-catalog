<?php

namespace App\Http\Controllers;

use App\Exceptions\LastActiveOwnerException;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\OwnerGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $connection = $user->getConnection();
        $fresh = $connection->transaction(function () use ($user, $data, $connection): User {
            if ($connection->getDriverName() === 'sqlite') {
                // SQLite ignores FOR UPDATE: reserve its write lock before
                // reading, as in the verification transaction.
                $connection->table($user->getTable())->where($user->getKeyName(), $user->getKey())
                    ->update([$user->getKeyName() => $user->getKey()]);
            }

            $fresh = User::on($connection->getName())->lockForUpdate()->findOrFail($user->getKey());
            $fresh->fill($data);

            if ($fresh->isDirty('email')) {
                // Verification may have completed since this request loaded
                // its user. Compare with the locked row so null is dirty even
                // when the request's original verification timestamp was null.
                $fresh->email_verified_at = null;
            }

            $fresh->save();

            return $fresh;
        }, attempts: 5);

        $user->setRawAttributes($fresh->getAttributes(), true);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Enforce the last-active-owner invariant BEFORE touching the session,
        // so a rejected deletion leaves the account and login state intact.
        try {
            OwnerGuard::mutate($user, function (User $locked): void {
                $locked->delete();
            });
        } catch (LastActiveOwnerException $e) {
            return Redirect::route('profile.edit')
                ->withErrors(['userDeletion' => $e->getMessage()], 'userDeletion');
        }

        // logout() would cycle the remember token and re-save (re-insert) the
        // just-deleted row; logoutCurrentDevice() clears the session without
        // writing to the users table. The remember cookie is useless once the
        // row is gone.
        Auth::guard('web')->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
