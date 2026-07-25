<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(): View
    {
        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->verifierCode2FA($request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => 'Code de vérification invalide ou expiré.',
            ]);
        }

        $request->session()->put('2fa_verified', true);

        return redirect()->intended(
            $user->doit_changer_mot_de_passe ? route('password.force') : route('dashboard')
        );
    }
}
