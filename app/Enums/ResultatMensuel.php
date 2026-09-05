<?php

namespace App\Enums;

/**
 * The qualitative rating a classe's titulaire picks for an élève's monthly
 * bulletin (see Bulletin::resultat_global) — the "Résultat global du mois"
 * options in the espace enseignant's comment panel.
 */
enum ResultatMensuel: string
{
    case TresBien = 'tres_bien';
    case Bien = 'bien';
    case AssezBien = 'assez_bien';
    case Passable = 'passable';
    case Mediocre = 'mediocre';
    case Mal = 'mal';

    public function label(): string
    {
        return match ($this) {
            self::TresBien => 'Très bien',
            self::Bien => 'Bien',
            self::AssezBien => 'Assez bien',
            self::Passable => 'Passable',
            self::Mediocre => 'Médiocre',
            self::Mal => 'Mal',
        };
    }

    /**
     * The little face/circle symbol shown next to the comment on the
     * printed bulletin (see files/bulletin.html's ratingSymbol()).
     */
    public function symbole(): string
    {
        return match ($this) {
            self::TresBien, self::Bien => '☺',
            self::AssezBien, self::Passable => '○',
            self::Mediocre, self::Mal => '◐',
        };
    }
}
