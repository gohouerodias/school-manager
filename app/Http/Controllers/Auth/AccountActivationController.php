<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Écran "Activer votre compte", distinct de la réinitialisation de mot de
 * passe (voir Auth\NewPasswordController) bien que les deux réutilisent le
 * même mécanisme de jeton Laravel (Password::reset()) — c'est le lien envoyé
 * par App\Notifications\ResetPasswordNotification qui pointe ici plutôt que
 * vers `password.reset` quand `User::estEnAttenteActivation()` est vrai
 * (compte fraîchement créé par un administrateur, jamais encore connecté).
 * Un texte différent à chaque étape évite la confusion "je n'ai jamais eu de
 * mot de passe, pourquoi me demande-t-on de le réinitialiser ?".
 */
class AccountActivationController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.activate-account', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->definirMotDePasse($password);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [$this->messageFor($status)],
            ]);
        }

        return redirect()->route('login')->with(
            'status',
            'Votre compte a été activé. Vous pouvez maintenant vous connecter.'
        );
    }

    private function messageFor(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'Ce lien d\'activation est invalide ou a expiré. Demandez à un administrateur de vous renvoyer une invitation.',
            Password::INVALID_USER => 'Aucun compte ne correspond à cette adresse e-mail.',
            Password::RESET_THROTTLED => 'Veuillez patienter avant de réessayer.',
            default => 'Impossible d\'activer le compte. Réessayez.',
        };
    }
}
