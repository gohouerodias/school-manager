<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always send back the same generic message, whether or not an
        // account exists for this address, to avoid leaking which emails
        // are registered.
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            "Si un compte existe pour cette adresse, un lien de réinitialisation vient de lui être envoyé."
        );
    }
}
