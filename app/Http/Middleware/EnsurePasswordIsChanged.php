<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a user still on their temporary first-login password to set a
 * personal one before reaching the rest of the application.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->doit_changer_mot_de_passe) {
            return redirect()->route('password.force');
        }

        return $next($request);
    }
}
