<?php

use App\Http\Middleware\AjouterEntetesSecurite;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\EnsureUserHasProfile;
use App\Support\LimitesEnvoi;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [AjouterEntetesSecurite::class]);

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            '2fa' => EnsureTwoFactorVerified::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'profile' => EnsureUserHasProfile::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The whole submission (usually several documents at once) exceeds
        // PHP's post_max_size: PHP has already discarded the body, so the
        // best we can do is send the agent back with a clear French
        // explanation instead of Symfony's generic "Oops! 413" page.
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $message = "L'envoi est trop volumineux pour le serveur (limite : "
                .LimitesEnvoi::enMo(LimitesEnvoi::octetsMaxParRequete())
                ." pour l'ensemble des fichiers). Rien n'a été enregistré : réduisez la taille des documents"
                ." ou ajoutez-les ensuite un par un depuis la fiche de l'apprenant.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['envoi' => $message]);
        });

        // Session expirée (page laissée ouverte longtemps) : retour direct à
        // la page de connexion avec une explication, au lieu d'une erreur
        // « 419 Page expirée » ou d'un échec silencieux. Les requêtes en
        // arrière-plan reçoivent un 401/419 JSON : resources/js/
        // session-expiree.js recharge alors la page.
        $messageSessionExpiree = "Votre session a expiré après une longue période d'inactivité. Reconnectez-vous pour continuer.";

        // Laravel convertit le jeton expiré en HttpException 419 avant
        // d'appeler ces règles : on reconnaît donc le 419 et sa cause.
        $exceptions->render(function (HttpException $exception, Request $request) use ($messageSessionExpiree) {
            if (! $exception->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $messageSessionExpiree], 419);
            }

            return redirect()->route('login')->with('session_expiree', true);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($messageSessionExpiree) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $messageSessionExpiree], 401);
            }

            // Le navigateur renvoie encore l'ancien cookie de session : c'est
            // une session expirée (et pas une première visite).
            $sessionExpiree = $request->hasCookie(config('session.cookie'));

            return redirect()->guest(route('login'))->with('session_expiree', $sessionExpiree);
        });
    })->create();
