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
     * Same underlying mécanisme (jeton + lien vers `password.reset`) pour
     * deux cas distincts, différenciés via `User::estEnAttenteActivation()` :
     * l'invitation d'un compte fraîchement créé (Comptes\UserAccountController::store())
     * — qui n'a encore jamais servi, donc jamais de mot de passe à
     * « réinitialiser » — et une vraie demande de réinitialisation par un
     * utilisateur existant (Auth\PasswordResetLinkController).
     */
    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $message = (new MailMessage)->greeting('Bonjour '.$notifiable->name.',');

        if ($notifiable->estEnAttenteActivation()) {
            $message
                ->subject('Votre compte a été créé — CSC Madre Trinidad')
                ->line("Un compte vient d'être créé pour vous sur le registre numérique du CSC Madre Trinidad.")
                ->line('Pour l\'activer, choisissez votre mot de passe en cliquant sur le bouton ci-dessous.')
                ->action('Créer mon mot de passe', $url)
                ->line('Ce lien expirera dans 60 minutes.')
                ->line("Si vous ne vous attendiez pas à cet e-mail, vous pouvez l'ignorer sans risque.");
        } else {
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
