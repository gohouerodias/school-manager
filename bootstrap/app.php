<?php

use App\Http\Middleware\AjouterEntetesSecurite;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\EnsureUserHasProfile;
use App\Support\LimitesEnvoi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

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
    })->create();
