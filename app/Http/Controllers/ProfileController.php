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
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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
