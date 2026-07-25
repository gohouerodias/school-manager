<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
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
            'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.'
        );
    }

    private function messageFor(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'Ce lien de réinitialisation est invalide ou a expiré.',
            Password::INVALID_USER => 'Aucun compte ne correspond à cette adresse e-mail.',
            Password::RESET_THROTTLED => 'Veuillez patienter avant de réessayer.',
            default => 'Impossible de réinitialiser le mot de passe. Réessayez.',
        };
    }
}
