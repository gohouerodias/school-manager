<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Même mécanisme de jeton Laravel (Password broker) pour deux cas
     * distincts, différenciés via `User::estEnAttenteActivation()` : le lien
     * pointe vers l'écran dédié `compte.activer` (voir
     * Auth\AccountActivationController) pour l'invitation d'un compte
     * fraîchement créé (Comptes\UserAccountController::store()) — qui n'a
     * encore jamais servi, donc jamais de mot de passe à « réinitialiser » —
     * et vers `password.reset` pour une vraie demande de réinitialisation
     * par un utilisateur existant (Auth\PasswordResetLinkController).
     */
    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)->greeting('Bonjour '.$notifiable->name.',');

        if ($notifiable->estEnAttenteActivation()) {
            // Lien vers l'écran dédié "Activer votre compte" (voir
            // Auth\AccountActivationController), pas vers `password.reset` :
            // ce compte n'a encore jamais eu de mot de passe, donc rien à
            // "réinitialiser" à proprement parler.
            $url = url(route('compte.activer', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $message
                ->subject('Votre compte a été créé — CSC Madre Trinidad')
                ->line("Un compte vient d'être créé pour vous sur le registre numérique du CSC Madre Trinidad.")
                ->line('Pour l\'activer, choisissez votre mot de passe en cliquant sur le bouton ci-dessous.')
                ->action('Activer mon compte', $url)
                ->line('Ce lien expirera dans 60 minutes.')
                ->line("Si vous ne vous attendiez pas à cet e-mail, vous pouvez l'ignorer sans risque.");
        } else {
            $url = url(route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $message
                ->subject('Réinitialisation de votre mot de passe — CSC Madre Trinidad')
                ->line('Vous recevez cet e-mail car une demande de réinitialisation de mot de passe a été effectuée pour votre compte.')
                ->action('Réinitialiser mon mot de passe', $url)
                ->line('Ce lien expirera dans 60 minutes.')
                ->line("Si vous n'êtes pas à l'origine de cette demande, aucune action n'est requise.");
        }

        return $message->salutation('Cordialement,<br>L\'équipe du CSC Madre Trinidad');
    }
}
