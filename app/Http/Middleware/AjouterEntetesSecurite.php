<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité ajoutés à toutes les réponses web :
 *  - X-Frame-Options : interdit d'afficher l'application dans une page
 *    d'un autre site (protection contre le « clickjacking »).
 *  - X-Content-Type-Options : le navigateur respecte le type annoncé des
 *    fichiers (documents des apprenants, PDF…) sans le deviner.
 *  - Referrer-Policy : les adresses internes (jetons d'activation…) ne
 *    sont jamais transmises en entier à un autre site.
 */
class AjouterEntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
