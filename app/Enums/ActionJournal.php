<?php

namespace App\Enums;

/**
 * Actions enregistrées dans le journal (Sécurité et Administration →
 * Journal des actions). La valeur est stockée telle quelle dans
 * journal_actions.action ; label() est ce qu'affiche l'écran.
 */
enum ActionJournal: string
{
    // Connexions
    case Connexion = 'connexion';
    case Deconnexion = 'deconnexion';
    case ConnexionEchouee = 'connexion_echouee';

    // Dossiers élèves
    case CreationEleve = 'creation_eleve';
    case ModificationEleve = 'modification_eleve';
    case ArchivageEleve = 'archivage_eleve';
    case DesarchivageEleve = 'desarchivage_eleve';
    case ChangementClasse = 'changement_classe';
    case AjoutDocument = 'ajout_document';
    case SuppressionDocument = 'suppression_document';
    case ImportEleves = 'import_eleves';

    // Notes et bulletins
    case SaisieNotes = 'saisie_notes';
    case SaisieEvaluations = 'saisie_evaluations';
    case ValidationBulletin = 'validation_bulletin';
    case DevalidationBulletin = 'devalidation_bulletin';
    case GenerationBulletins = 'generation_bulletins';
    case GenerationBulletinsAnnuels = 'generation_bulletins_annuels';
    case DecisionPassage = 'decision_passage';

    public function label(): string
    {
        return match ($this) {
            self::Connexion => 'Connexion',
            self::Deconnexion => 'Déconnexion',
            self::ConnexionEchouee => 'Connexion échouée',
            self::CreationEleve => "Création d'une fiche apprenant",
            self::ModificationEleve => "Modification d'une fiche apprenant",
            self::ArchivageEleve => "Archivage d'un apprenant",
            self::DesarchivageEleve => "Désarchivage d'un apprenant",
            self::ChangementClasse => 'Changement de classe',
            self::AjoutDocument => "Ajout d'un document",
            self::SuppressionDocument => "Suppression d'un document",
            self::ImportEleves => 'Import Excel des apprenants',
            self::SaisieNotes => 'Saisie de notes',
            self::SaisieEvaluations => "Saisie d'évaluations (maternelle)",
            self::ValidationBulletin => "Validation d'un bulletin",
            self::DevalidationBulletin => "Dévalidation d'un bulletin",
            self::GenerationBulletins => 'Génération des bulletins mensuels',
            self::GenerationBulletinsAnnuels => 'Génération des bulletins annuels',
            self::DecisionPassage => 'Décision de passage',
        };
    }
}
