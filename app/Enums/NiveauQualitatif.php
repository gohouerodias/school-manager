<?php

namespace App\Enums;

/**
 * L'échelle d'appréciation qualitative de la maternelle (grille d'évaluation
 * mensuelle) — TS / S / PS pour chaque domaine d'évaluation, à la place
 * d'une note chiffrée (voir App\Models\EvaluationDomaine::valeur).
 */
enum NiveauQualitatif: string
{
    case TresSatisfaisant = 'ts';
    case Satisfaisant = 's';
    case PeuSatisfaisant = 'ps';

    public function label(): string
    {
        return match ($this) {
            self::TresSatisfaisant => 'Très satisfaisant',
            self::Satisfaisant => 'Satisfaisant',
            self::PeuSatisfaisant => 'Peu satisfaisant',
        };
    }

    public function abrege(): string
    {
        return match ($this) {
            self::TresSatisfaisant => 'TS',
            self::Satisfaisant => 'S',
            self::PeuSatisfaisant => 'PS',
        };
    }

    /**
     * Le symbole affiché sur le bulletin imprimé (voir le document de
     * référence "Grille d'évaluation mensuelle" : ☺ / ○ / ◑).
     */
    public function symbole(): string
    {
        return match ($this) {
            self::TresSatisfaisant => '☺',
            self::Satisfaisant => '○',
            self::PeuSatisfaisant => '◑',
        };
    }
}
