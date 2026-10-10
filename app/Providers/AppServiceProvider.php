<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurerLimitesDeTentatives();
    }

    /**
     * Protection contre les essais de mots de passe en rafale (force brute) :
     * « connexion » limite chaque couple e-mail + adresse IP, et chaque IP
     * dans son ensemble ; « sensible » couvre mot de passe oublié,
     * réinitialisation, activation de compte et code 2FA. Une fois la limite
     * atteinte, retour au formulaire avec un message en français plutôt que
     * la page anglaise « 429 Too Many Requests ».
     */
    private function configurerLimitesDeTentatives(): void
    {
        $retourAvecMessage = fn (string $champ) => function (Request $request, array $headers) use ($champ) {
            $secondes = (int) ($headers['Retry-After'] ?? 60);

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'code']))
                ->withErrors([$champ => "Trop de tentatives. Réessayez dans {$secondes} seconde(s)."]);
        };

        RateLimiter::for('connexion', fn (Request $request) => [
            Limit::perMinute(5)
                ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
                ->response($retourAvecMessage('email')),
            Limit::perMinute(20)->by('ip:'.$request->ip())->response($retourAvecMessage('email')),
        ]);

        RateLimiter::for('sensible', fn (Request $request) => Limit::perMinute(6)
            ->by($request->ip())
            ->response($retourAvecMessage($request->routeIs('2fa.verify') ? 'code' : 'email')));
    }
}
