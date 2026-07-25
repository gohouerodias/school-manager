<?php

namespace App\Http\Controllers\Auth;

use App\Enums\StatutUtilisateur;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects. Vérifiez votre adresse e-mail et votre mot de passe.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if ($user->statut === StatutUtilisateur::Archive) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => "Ce compte a été archivé. Contactez l'administration de l'établissement.",
            ]);
        }

        $request->session()->forget('2fa_verified');

        return redirect()->intended($this->nextStepAfterLogin($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function nextStepAfterLogin(User $user): string
    {
        if ($user->deux_fa_actif) {
            return route('2fa.challenge');
        }

        if ($user->doit_changer_mot_de_passe) {
            return route('password.force');
        }

        return route('dashboard');
    }
}
