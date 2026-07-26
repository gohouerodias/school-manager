<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Self-service "Mon profil" popover in the topbar: any authenticated user
 * can update their own name/telephone, but never their own e-mail (see
 * UpdateProfileRequest). Only an administrator can change a user's e-mail,
 * from the account management screen (UserAccountController::update).
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'telephone' => $validated['telephone'] ?? null,
        ]);

        return back()->with('toast', 'Votre profil a été mis à jour.');
    }
}
