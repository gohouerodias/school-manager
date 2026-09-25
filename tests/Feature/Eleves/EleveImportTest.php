<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function ecrireCsvImport(array $lignes): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'import').'.csv';
    $handle = fopen($chemin, 'w');
    fputcsv($handle, ['Matricule', 'Nom', 'Prénom', 'Sexe', 'Date de naissance', 'Classe', 'Statut']);
    foreach ($lignes as $ligne) {
        fputcsv($handle, $ligne);
    }
    fclose($handle);

    return new UploadedFile($chemin, 'apprenants.csv', 'text/csv', null, true);
}

test('importing a new matricule creates a new fiche', function () {
    $admin = User::factory()->administrateur()->create();

    $fichier = ecrireCsvImport([
        ['CSC-999001', 'Adjovi', 'Marie', 'F', '12/05/2015', '', 'Actif'],
    ]);

    $this->actingAs($admin)
        ->post(route('eleves.import.store'), ['fichier' => $fichier])
        ->assertOk()
        ->assertSee('1', false);

    $eleve = Eleve::query()->where('matricule', 'CSC-999001')->first();
    expect($eleve)->not->toBeNull();
    expect($eleve->nom)->toBe('Adjovi');
    expect($eleve->prenom)->toBe('Marie');
});

test('importing an existing matricule updates the fiche instead of duplicating it', function () {
    $admin = User::factory()->administrateur()->create();
    $eleve = Eleve::factory()->create(['matricule' => 'CSC-999002', 'nom' => 'Ancien nom']);

    $fichier = ecrireCsvImport([
        ['CSC-999002', 'Nouveau nom', 'Prénom', 'F', '01/01/2016', '', 'Actif'],
    ]);

    $this->actingAs($admin)
        ->post(route('eleves.import.store'), ['fichier' => $fichier])
        ->assertOk();

    expect(Eleve::query()->where('matricule', 'CSC-999002')->count())->toBe(1);
    expect($eleve->fresh()->nom)->toBe('Nouveau nom');
});

test('a row missing required fields is reported as an error and skipped', function () {
    $admin = User::factory()->administrateur()->create();

    $fichier = ecrireCsvImport([
        ['CSC-999003', '', 'Prénom', 'F', '01/01/2016', '', 'Actif'],
    ]);

    $response = $this->actingAs($admin)
        ->post(route('eleves.import.store'), ['fichier' => $fichier]);

    $response->assertOk();
    $response->assertSee('Ligne 2', false);
    expect(Eleve::query()->where('matricule', 'CSC-999003')->exists())->toBeFalse();
});

test('the classe column assigns the eleve to the named classe in the active année only', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeActive->id, 'niveau_id' => Niveau::factory(), 'nom' => 'CI A']);

    $fichier = ecrireCsvImport([
        ['CSC-999004', 'Houngbo', 'Paul', 'M', '10/03/2017', 'CI A', 'Actif'],
    ]);

    $this->actingAs($admin)
        ->post(route('eleves.import.store'), ['fichier' => $fichier])
        ->assertOk();

    $eleve = Eleve::query()->where('matricule', 'CSC-999004')->first();
    $inscription = Inscription::query()->where('eleve_id', $eleve->id)->first();

    expect($inscription)->not->toBeNull();
    expect($inscription->classe_id)->toBe($classe->id);
});
