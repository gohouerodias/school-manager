<?php

use App\Models\AnneeAcademique;
use App\Models\ChampPersonnalise;
use App\Models\DomaineEvaluation;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\TypeDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeds only reference data, no user accounts and no fake demo data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Niveau::count())->toBe(12)
        ->and(AnneeAcademique::count())->toBe(1)
        ->and(Examen::count())->toBe(1)
        ->and(TypeDocument::count())->toBe(7)
        ->and(Matiere::count())->toBe(8)
        ->and(DomaineEvaluation::count())->toBe(11)
        ->and(ChampPersonnalise::count())->toBe(7)
        ->and(User::count())->toBe(0)
        ->and(Eleve::count())->toBe(0);
});
