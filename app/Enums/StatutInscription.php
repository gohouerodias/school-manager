<?php

namespace App\Enums;

/**
 * Nature de cette ligne du parcours scolaire d'un apprenant (une par
 * Inscription — voir Inscription et Models\Eleve::inscriptions()), affichée
 * sur la frise chronologique de l'onglet « Parcours scolaire » de la fiche
 * apprenant. Distinct de DecisionAnnuelle : la décision est le résultat de
 * fin d'année (Admis/Redouble/Exclu), le statut est la nature de l'entrée
 * dans cette classe pour cette année-là.
 *
 * "Normal" et "Redoublant" sont fixés automatiquement à la création de
 * l'Inscription par PromotionAnnuelleService::promouvoir() (bascule
 * automatique au démarrage de l'année suivante, selon que l'apprenant a été
 * admis au niveau supérieur ou redouble le même niveau) — voir aussi
 * Eleves\EleveClasseController::update(), qui utilise "Normal" par défaut
 * pour toute première affectation de classe. "Transfert entrant",
 * "Transfert sortant" et "Abandon" ne peuvent pas être déduits
 * automatiquement : ce sont des corrections manuelles, faites depuis la
 * frise (voir Eleves\InscriptionStatutController).
 */
enum StatutInscription: string
{
    case Normal = 'normal';
    case Redoublant = 'redoublant';
    case TransfertEntrant = 'transfert_entrant';
    case TransfertSortant = 'transfert_sortant';
    case Abandon = 'abandon';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Redoublant => 'Redoublant',
            self::TransfertEntrant => 'Transféré entrant',
            self::TransfertSortant => 'Transféré sortant',
            self::Abandon => 'Abandon',
        };
    }

    /**
     * Un badge n'est affiché sur la frise que pour les statuts qui sortent
     * du cours normal des choses — "Normal" reste silencieux (aucun badge)
     * pour ne pas surcharger l'écran d'une ligne par année qui ne dit rien
     * d'utile.
     */
    public function estNotable(): bool
    {
        return $this !== self::Normal;
    }
}
