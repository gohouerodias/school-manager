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
