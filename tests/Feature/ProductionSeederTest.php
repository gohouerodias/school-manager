<?php

use App\Models\AnneeAcademique;
use App\Models\ChampPersonnalise;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\TypeDocument;
use App\Models\User;
use Database\Seeders\ProductionSeeder;

test('seeds only reference data and the 3 named administrative accounts, no fake demo data', function () {
    $this->seed(ProductionSeeder::class);

    expect(Niveau::count())->toBe(12)
        ->and(AnneeAcademique::count())->toBe(1)
        ->and(Examen::count())->toBe(1)
        ->and(TypeDocument::count())->toBe(7)
        ->and(Matiere::count())->toBe(8)
        ->and(ChampPersonnalise::count())->toBe(7)
        ->and(User::count())->toBe(3)
        ->and(Eleve::count())->toBe(0);

    $admin = User::where('email', 'admin@cscmadretrinidad.bj')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->doit_changer_mot_de_passe)->toBeTrue();
});
