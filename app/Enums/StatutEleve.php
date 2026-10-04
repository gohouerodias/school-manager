<?php

namespace App\Enums;

enum StatutEleve: string
{
    // Incomplete fiche saved with the wizard's « Sauvegarder en brouillon »
    // (only nom + prénom required, see SaveEleveWizardRequest::
    // enregistreEnBrouillon()). Resumed via the élèves list's "Continuer"
    // action and promoted to Actif once completed with « Terminer ».
    case Brouillon = 'brouillon';
    case Actif = 'actif';
    case Archive = 'archive';
}
