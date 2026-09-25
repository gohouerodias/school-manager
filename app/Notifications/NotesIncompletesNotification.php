<?php

namespace App\Notifications;

use App\Models\Classe;
use App\Models\Examen;
use Illuminate\Notifications\Notification;

/**
 * Envoyée à l'enseignant responsable (titulaire pour une classe primaire/
 * maternelle, enseignant de la matière pour une classe collège — voir
 * App\Console\Commands\NotifierNotesIncompletesCommand et
 * StoreAffectationEnseignantRequest::classeEstEnModeEntiere()) 14 jours
 * avant la date limite de saisie d'un examen, si des notes manquent encore.
 * Canal "database" uniquement pour l'instant (voir la migration créant la
 * table `notifications`) — pas d'e-mail, aucun serveur SMTP réel n'étant
 * configuré pour cette application.
 */
class NotesIncompletesNotification extends Notification
{
    public function __construct(
        private readonly Classe $classe,
        private readonly Examen $examen,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $joursRestants = now()->startOfDay()->diffInDays($this->examen->date_limite_saisie->startOfDay(), false);

        return [
            'titre' => 'Notes incomplètes',
            'message' => sprintf(
                "Il reste %d jour(s) pour saisir les notes de %s — %s (%s) : certaines notes n'ont pas encore été renseignées.",
                max(0, $joursRestants),
                $this->classe->niveau->libelle,
                $this->classe->nom,
                $this->examen->date_examen->translatedFormat('F Y'),
            ),
            'url' => route('enseignant.classes.show', $this->classe),
            'classe_id' => $this->classe->id,
            'examen_id' => $this->examen->id,
        ];
    }
}
