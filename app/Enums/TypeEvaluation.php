<?php

namespace App\Enums;

enum TypeEvaluation: string
{
    case Interrogation = 'interrogation';
    case Devoir = 'devoir';
    case CompositionGenerale = 'composition_generale';
    case EvaluationMensuelle = 'evaluation_mensuelle';

    public function label(): string
    {
        return match ($this) {
            self::Interrogation => 'Interrogation',
            self::Devoir => 'Devoir',
            self::CompositionGenerale => 'Composition générale',
            self::EvaluationMensuelle => 'Évaluation mensuelle',
        };
    }
}
