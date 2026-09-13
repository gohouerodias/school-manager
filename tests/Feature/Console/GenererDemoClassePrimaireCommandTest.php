<?php

use App\Enums\StatutBulletin;
use App\Enums\StatutInscription;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;

test('the demo command generates 3 années with a full classe, notes, validated monthly bulletins and a décision per année', function () {
    $this->artisan('demo:classe-primaire')->assertExitCode(0);

    expect(User::where('email', 'titulaire.demo.cm1a@ecole.test')->exists())->toBeTrue();
    expect(Eleve::where('matricule', 'like', 'DEMO-CM1-%')->count())->toBe(3);

    $annees = AnneeAcademique::whereIn('libelle', ['Démo 2022-2023', 'Démo 2023-2024', 'Démo 2024-2025'])->get();
    expect($annees)->toHaveCount(3);
    // Aucune des années générées ne doit devenir active toute seule : ça
    // perturberait l'année en cours réelle de l'établissement.
    expect($annees->every(fn (AnneeAcademique $a) => $a->est_active === false))->toBeTrue();

    foreach ($annees as $annee) {
        $classe = Classe::where('annee_academique_id', $annee->id)->where('nom', 'CM1 A')->firstOrFail();
        $inscriptions = Inscription::where('classe_id', $classe->id)->get();

        expect($inscriptions)->toHaveCount(3);
        expect(Bulletin::whereIn('inscription_id', $inscriptions->pluck('id'))->where('statut', StatutBulletin::Valide)->count())->toBe(9); // 3 élèves x 3 examens

        $inscriptions->each(function (Inscription $inscription) {
            expect($inscription->moyenne_annuelle)->not->toBeNull();
            expect($inscription->decision)->not->toBeNull();
        });
    }

    $anneeRedoublement = $annees->firstWhere('libelle', 'Démo 2023-2024');
    $classeRedoublement = Classe::where('annee_academique_id', $anneeRedoublement->id)->firstOrFail();
    expect(Inscription::where('classe_id', $classeRedoublement->id)->pluck('statut')->unique()->all())
        ->toEqual([StatutInscription::Redoublant]);
});

test('running the demo command twice is a safe no-op the second time', function () {
    $this->artisan('demo:classe-primaire')->assertExitCode(0);
    $response = $this->artisan('demo:classe-primaire');

    $response->assertExitCode(1);
    expect(User::where('email', 'titulaire.demo.cm1a@ecole.test')->count())->toBe(1);
    expect(Eleve::where('matricule', 'like', 'DEMO-CM1-%')->count())->toBe(3);
});

test('the --supprimer option removes everything the demo command generated', function () {
    $this->artisan('demo:classe-primaire')->assertExitCode(0);

    $this->artisan('demo:classe-primaire --supprimer')->assertExitCode(0);

    expect(User::where('email', 'titulaire.demo.cm1a@ecole.test')->exists())->toBeFalse();
    expect(Eleve::where('matricule', 'like', 'DEMO-CM1-%')->count())->toBe(0);
    expect(AnneeAcademique::whereIn('libelle', ['Démo 2022-2023', 'Démo 2023-2024', 'Démo 2024-2025'])->count())->toBe(0);
    expect(Classe::where('nom', 'CM1 A')->count())->toBe(0);
    expect(Inscription::count())->toBe(0);
    expect(Bulletin::count())->toBe(0);
});

test('the --supprimer option never touches the shared niveau/matières reference data', function () {
    $this->artisan('demo:classe-primaire')->assertExitCode(0);

    $this->artisan('demo:classe-primaire --supprimer')->assertExitCode(0);

    $this->assertDatabaseHas('niveaux', ['libelle' => 'CM1']);
    $this->assertDatabaseHas('matieres', ['nom' => 'Français']);
    $this->assertDatabaseHas('matieres', ['nom' => 'Mathématiques']);
});

test('--supprimer is a no-op when nothing was generated', function () {
    $response = $this->artisan('demo:classe-primaire --supprimer');

    $response->assertExitCode(1);
});
