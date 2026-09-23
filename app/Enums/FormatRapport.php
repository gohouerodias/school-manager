<?php

namespace App\Enums;

enum FormatRapport: string
{
    case Pdf = 'pdf';
    case Excel = 'excel';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Excel => 'Excel',
        };
    }
}
