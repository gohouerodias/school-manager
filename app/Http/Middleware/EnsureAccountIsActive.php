<?php

namespace App\Http\Middleware;

use App\Enums\StatutUtilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out and rejects any authenticated user whose account has been
 * archived (e.g. by an administrator mid-session).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->statut === StatutUtilisateur::Archive) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => "Ce compte a été archivé. Contactez l'administration de l'établissement.",
            ]);
        }

        return $next($request);
    }
}
