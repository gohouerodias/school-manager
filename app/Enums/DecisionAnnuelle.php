<?php

namespace App\Enums;

enum DecisionAnnuelle: string
{
    case Admis = 'admis';
    case Redouble = 'redouble';
    case Exclu = 'exclu';
}
