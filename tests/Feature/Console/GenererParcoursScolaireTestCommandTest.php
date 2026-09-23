<?php

use App\Enums\StatutInscription;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('when no année is active, the command creates and activates one, plus a fully dedicated prior année', function () {
    Storage::fake('local');

    $this->artisan('test-data:parcours-scolaire')->assertExitCode(0);

    expect(User::where('email', 'titulaire.test.parcours@ecole.test')->exists())->toBeTrue();

    $anneeActive = AnneeAcademique::where('est_active', true)->firstOrFail();
    $anneePrecedente = AnneeAcademique::where('libelle', 'like', 'Vérif. parcours %')->firstOrFail();
    expect($anneePrecedente->est_active)->toBeFalse();

    $classeActive = Classe::where('annee_academique_id', $anneeActive->id)->where('nom', 'Vérif. parcours')->firstOrFail();
    $classePrecedente = Classe::where('annee_academique_id', $anneePrecedente->id)->where('nom', 'Vérif. parcours')->firstOrFail();

    $eleveContinu = Eleve::where('matricule', 'TEST-PARCOURS-01')->firstOrFail();
    $eleveTransfert = Eleve::where('matricule', 'TEST-PARCOURS-02')->firstOrFail();

    // Parcours normal : inscrit les 2 années, moyenne/décision finalisées
    // pour l'année précédente.
    $inscriptionPrecedente = Inscription::where(['eleve_id' => $eleveContinu->id, 'classe_id' => $classePrecedente->id])->firstOrFail();
    expect($inscriptionPrecedente->statut)->toBe(StatutInscription::Normal);
    expect($inscriptionPrecedente->moyenne_annuelle)->not->toBeNull();
    expect($inscriptionPrecedente->decision)->not->toBeNull();
    Inscription::where(['eleve_id' => $eleveContinu->id, 'classe_id' => $classeActive->id])->firstOrFail();

    // Transféré entrant : jamais inscrit l'année précédente, avec un
    // document justificatif rattaché à son inscription de l'année active.
    expect(Inscription::where(['eleve_id' => $eleveTransfert->id, 'classe_id' => $classePrecedente->id])->exists())->toBeFalse();
    $inscriptionTransfert = Inscription::where(['eleve_id' => $eleveTransfert->id, 'classe_id' => $classeActive->id])->firstOrFail();
    expect($inscriptionTransfert->statut)->toBe(StatutInscription::TransfertEntrant);

    $document = DocumentNumerique::where('inscription_id', $inscriptionTransfert->id)->firstOrFail();
    expect($document->eleve_id)->toBe($eleveTransfert->id);
    Storage::disk('local')->assertExists($document->chemin_fichier);
});

test('when a real année is already active, the command reuses it without touching its est_active flag', function () {
    Storage::fake('local');

    $anneeReelle = AnneeAcademique::factory()->create(['est_active' => true, 'libelle' => '2026-2027']);
    $classeReelle = Classe::factory()->create(['annee_academique_id' => $anneeReelle->id, 'nom' => 'A']);
    Eleve::factory()->count(5)->create();

    $this->artisan('test-data:parcours-scolaire')->assertExitCode(0);

    // La vraie année active n'a jamais été touchée : toujours active, même
    // libellé, sa propre classe "A" toujours là et inchangée.
    expect($anneeReelle->fresh()->est_active)->toBeTrue();
    expect(AnneeAcademique::where('est_active', true)->count())->toBe(1);
    $this->assertDatabaseHas('classes', ['id' => $classeReelle->id, 'nom' => 'A']);

    // Notre classe de test a été ajoutée à côté, dans cette même année.
    Classe::where('annee_academique_id', $anneeReelle->id)->where('nom', 'Vérif. parcours')->firstOrFail();
});

test('--supprimer removes everything generated but leaves a reused real année active and untouched', function () {
    Storage::fake('local');

    $anneeReelle = AnneeAcademique::factory()->create(['est_active' => true, 'libelle' => '2026-2027']);

    $this->artisan('test-data:parcours-scolaire')->assertExitCode(0);
    $this->artisan('test-data:parcours-scolaire --supprimer')->assertExitCode(0);

    expect(User::where('email', 'titulaire.test.parcours@ecole.test')->exists())->toBeFalse();
    expect(Eleve::whereIn('matricule', ['TEST-PARCOURS-01', 'TEST-PARCOURS-02'])->count())->toBe(0);
    expect(Classe::where('nom', 'Vérif. parcours')->count())->toBe(0);
    expect(AnneeAcademique::where('libelle', 'like', 'Vérif. parcours %')->count())->toBe(0);
    expect(DocumentNumerique::count())->toBe(0);

    // La vraie année, elle, est intacte.
    expect($anneeReelle->fresh())->not->toBeNull();
    expect($anneeReelle->fresh()->est_active)->toBeTrue();
});

test('running the command twice is a safe no-op the second time', function () {
    Storage::fake('local');

    $this->artisan('test-data:parcours-scolaire')->assertExitCode(0);
    $response = $this->artisan('test-data:parcours-scolaire');

    $response->assertExitCode(1);
    expect(Eleve::whereIn('matricule', ['TEST-PARCOURS-01', 'TEST-PARCOURS-02'])->count())->toBe(2);
});

test('--supprimer is a no-op when nothing was generated', function () {
    $response = $this->artisan('test-data:parcours-scolaire --supprimer');

    $response->assertExitCode(1);
});
