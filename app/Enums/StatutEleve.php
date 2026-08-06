<?php

namespace App\Enums;

enum StatutEleve: string
{
    // Legacy status: the fiche élève wizard used to let a fiche be saved as
    // an incomplete draft before "Terminer" was confirmed. That "Sauvegarder
    // le brouillon" action has since been removed — the wizard now always
    // requires full validation to save, whether creating or editing — so
    // this case is no longer reachable from the UI. It's kept only so any
    // pre-existing Brouillon row can still be displayed (see the élèves
    // list's "Continuer" action) and promoted to Actif by editing it.
    case Brouillon = 'brouillon';
    case Actif = 'actif';
    case Archive = 'archive';
}
