<?php

namespace App\Enums;

/**
 * Avancement d'une DemandeGenerationBulletin traitée par
 * App\Jobs\GenererBulletinsClasseJob — EnAttente dès la demande (avant que
 * le worker de la file ne la prenne), EnCours pendant le traitement
 * (bulletin par bulletin, voir DemandeGenerationBulletin::traites/total),
 * puis Termine ou Echec.
 */
enum StatutGenerationBulletin: string
{
    case EnAttente = 'en_attente';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Echec = 'echec';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::EnCours => 'En cours',
            self::Termine => 'Terminée',
            self::Echec => 'Échec',
        };
    }
}
