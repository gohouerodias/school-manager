<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one or more Utilisateur profiles, e.g.
 * `->middleware('profile:administrateur')`.
 */
class EnsureUserHasProfile
{
    public function handle(Request $request, Closure $next, string ...$profiles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->profil->value, $profiles, true)) {
            abort(403, "Vous n'avez pas accès à cette section.");
        }

        return $next($request);
    }
}
