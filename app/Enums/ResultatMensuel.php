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

    /**
     * The 3-row "Symboles interprétant les résultats de l'apprenant" legend
     * printed on the maternelle bulletin (voir le modèle papier de
     * référence) — chaque symbole regroupe les deux valeurs adjacentes de
     * l'échelle qui partagent ce même symbole (voir symbole()). Utilisé
     * uniquement pour l'affichage : la valeur stockée en base
     * (Bulletin::resultat_global) reste l'une des 6 valeurs de cette échelle,
     * quel que soit le cycle — voir eleves/bulletins/_papier.blade.php.
     *
     * @return array<int, array{symbole: string, label: string, valeurs: array<int, self>}>
     */
    public static function groupesSymboles(): array
    {
        return [
            ['symbole' => '☺', 'label' => 'Très bien / Bien', 'valeurs' => [self::TresBien, self::Bien]],
            ['symbole' => '○', 'label' => 'Assez bien / Passable', 'valeurs' => [self::AssezBien, self::Passable]],
            ['symbole' => '◐', 'label' => 'Médiocre / Mal', 'valeurs' => [self::Mediocre, self::Mal]],
        ];
    }
}
