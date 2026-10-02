<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Mail\Markdown;

/**
 * Vérifie que les emails (réinitialisation de mot de passe, invitations de
 * comptes — même notification, `App\Notifications\ResetPasswordNotification`)
 * utilisent bien le logo de l'école et non plus le logo Laravel par défaut,
 * et une formule de politesse en français plutôt que le "Regards," anglais
 * par défaut. Rendu directement via `Illuminate\Mail\Markdown`, exactement
 * comme le fait `Illuminate\Notifications\Channels\MailChannel::buildMarkdownHtml()`
 * en conditions réelles.
 */
test('the mail template shows the school logo instead of the Laravel logo', function () {
    $user = User::factory()->create();
    $mailMessage = (new ResetPasswordNotification('un-jeton'))->toMail($user);

    $html = app(Markdown::class)->render(
        $mailMessage->markdown,
        $mailMessage->data()
    )->toHtml();

    expect($html)->toContain('logo-cscmt.jpg')
        ->and($html)->not->toContain('laravel.com/img/notification-logo')
        ->and($html)->toContain('CSC Madre Trinidad')
        ->and($html)->not->toContain('Regards,');
});

/**
 * Le petit texte sous le bouton d'action ("If you're having trouble
 * clicking...") vient de la traduction par défaut de Laravel
 * (vendor/laravel/framework/.../Notifications/resources/views/email.blade.php,
 * `@lang(...)`), pas d'un vendor view publié — traduit via lang/fr.json
 * (App_LOCALE=fr, voir config/app.php) plutôt qu'en surchargeant le template.
 */
test('the "trouble clicking the button" subcopy is shown in French', function () {
    $user = User::factory()->create();
    $mailMessage = (new ResetPasswordNotification('un-jeton'))->toMail($user);

    $html = app(Markdown::class)->render(
        $mailMessage->markdown,
        $mailMessage->data()
    )->toHtml();

    expect($html)->toContain('Si vous rencontrez des difficultés pour cliquer sur le bouton')
        ->and($html)->not->toContain("If you're having trouble clicking");
});

/**
 * `estEnAttenteActivation()` (jamais connecté) distingue les deux usages de
 * cette même notification — voir son docblock dans `ResetPasswordNotification`.
 */
test('a freshly invited account (never logged in) gets an account-creation message', function () {
    $user = User::factory()->create(['derniere_connexion_at' => null]);
    $mailMessage = (new ResetPasswordNotification('un-jeton'))->toMail($user);

    expect($mailMessage->subject)->toBe('Votre compte a été créé — CSC Madre Trinidad')
        ->and(implode(' ', $mailMessage->introLines))->toContain("Un compte vient d'être créé pour vous")
        ->and($mailMessage->actionText)->toBe('Activer mon compte')
        ->and($mailMessage->actionUrl)->toContain(route('compte.activer', ['token' => 'un-jeton', 'email' => $user->email], false))
        ->and(implode(' ', $mailMessage->introLines))->not->toContain('réinitialisation');
});

test('an existing user requesting a password reset gets the reset message', function () {
    $user = User::factory()->create(['derniere_connexion_at' => now()->subDays(3)]);
    $mailMessage = (new ResetPasswordNotification('un-jeton'))->toMail($user);

    expect($mailMessage->subject)->toBe('Réinitialisation de votre mot de passe — CSC Madre Trinidad')
        ->and(implode(' ', $mailMessage->introLines))->toContain('demande de réinitialisation de mot de passe')
        ->and($mailMessage->actionText)->toBe('Réinitialiser mon mot de passe');
});
