<?php

namespace App\Console\Commands;

use App\Enums\CycleNiveau;
use App\Models\AffectationEnseignant;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\User;
use App\Notifications\NotesIncompletesNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Prévient l'enseignant responsable (titulaire pour une classe primaire/
 * maternelle, enseignant de la matière pour une classe collège — voir
 * StoreAffectationEnseignantRequest::classeEstEnModeEntiere()) quand des
 * notes manquent encore à 14 jours de la date limite de saisie d'un examen.
 * Maternelle exclue (pas de notes chiffrées, voir Classe::estMaternelle() —
 * les domaines qualitatifs n'ont pas cette même notion de "date limite de
 * saisie" à surveiller ici).
 *
 * Pensée pour tourner une fois par jour (voir routes/console.php) : ne
 * renvoie jamais deux fois la même notification à un même enseignant pour
 * la même classe/le même examen, même si la commande est relancée
 * manuellement plusieurs fois le même jour.
 */
class NotifierNotesIncompletesCommand extends Command
{
    protected $signature = 'notifications:notes-incompletes';

    protected $description = "Notifie l'enseignant responsable quand des notes sont encore manquantes à 14 jours de la date limite de saisie d'un examen";

    public function handle(): int
    {
        $dateCible = now()->addDays(14)->toDateString();

        $examens = Examen::query()->whereDate('date_limite_saisie', $dateCible)->get();

        if ($examens->isEmpty()) {
            $this->components->info("Aucun examen n'a sa date limite de saisie dans 14 jours ({$dateCible}) — rien à faire.");

            return self::SUCCESS;
        }

        $notifiesCount = 0;

        foreach ($examens as $examen) {
            $classes = Classe::query()
                ->where('annee_academique_id', $examen->annee_academique_id)
                ->with('niveau')
                ->get()
                ->reject(fn (Classe $classe) => $classe->estMaternelle());

            foreach ($classes as $classe) {
                $notifiesCount += $this->notifierPourClasse($classe, $examen);
            }
        }

        $this->components->info("{$notifiesCount} notification(s) envoyée(s) pour les examens dont la date limite de saisie est le {$dateCible}.");

        return self::SUCCESS;
    }

    private function notifierPourClasse(Classe $classe, Examen $examen): int
    {
        $inscriptionCount = Inscription::query()->where('classe_id', $classe->id)->count();
        if ($inscriptionCount === 0) {
            return 0;
        }

        $classeMatieres = ClasseMatiere::query()->where('classe_id', $classe->id)->get();

        // Une matière "incomplète" : moins de notes saisies que d'inscrits,
        // pour cet examen — même logique que Classe::notesCompletesPour(),
        // mais agrégée par matière plutôt que par élève.
        $matieresIncompletes = $classeMatieres->filter(function (ClasseMatiere $classeMatiere) use ($examen, $inscriptionCount) {
            $notesSaisies = Note::query()
                ->where('classe_matiere_id', $classeMatiere->id)
                ->where('examen_id', $examen->id)
                ->count();

            return $notesSaisies < $inscriptionCount;
        });

        if ($matieresIncompletes->isEmpty()) {
            return 0;
        }

        $modeEntiere = in_array($classe->niveau->cycle, [CycleNiveau::Maternelle, CycleNiveau::Primaire], true);

        if ($modeEntiere) {
            // Primaire : un seul titulaire pour toute la classe, peu importe
            // combien de matières précises manquent.
            $titulaire = AffectationEnseignant::query()
                ->where('classe_id', $classe->id)
                ->where('est_professeur_principal', true)
                ->first()
                ?->enseignant;

            return $titulaire && $this->notifierUneFois($titulaire, $classe, $examen) ? 1 : 0;
        }

        // Collège : un enseignant par matière — seuls ceux dont la matière
        // est encore incomplète sont notifiés.
        $enseignants = AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->whereIn('matiere_id', $matieresIncompletes->pluck('matiere_id')->unique())
            ->with('enseignant')
            ->get()
            ->pluck('enseignant')
            ->filter()
            ->unique('id');

        return $enseignants->sum(fn (User $enseignant) => $this->notifierUneFois($enseignant, $classe, $examen) ? 1 : 0);
    }

    /**
     * `false` si cet enseignant a déjà été notifié pour cette même classe et
     * ce même examen (aucune notification envoyée) — évite tout doublon,
     * y compris si la commande est relancée plusieurs fois le même jour.
     */
    private function notifierUneFois(User $enseignant, Classe $classe, Examen $examen): bool
    {
        $dejaNotifie = DatabaseNotification::query()
            ->where('notifiable_type', $enseignant->getMorphClass())
            ->where('notifiable_id', $enseignant->id)
            ->where('type', NotesIncompletesNotification::class)
            ->where('data->classe_id', $classe->id)
            ->where('data->examen_id', $examen->id)
            ->exists();

        if ($dejaNotifie) {
            return false;
        }

        $enseignant->notify(new NotesIncompletesNotification($classe, $examen));

        return true;
    }
}
