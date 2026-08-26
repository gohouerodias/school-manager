<?php

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Models\AnneeAcademique;
use App\Models\Examen;
use App\Models\User;

test('an administrateur can create an examen mensuel for the système primaire', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $response = $this->actingAs($admin)->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ]);

    $response->assertRedirect();
    $examen = Examen::query()->firstOrFail();
    expect($examen->annee_academique_id)->toBe($anneeActive->id);
    expect($examen->systeme)->toBe(SystemeScolaire::Primaire);
    expect($examen->type)->toBe(TypeEvaluation::EvaluationMensuelle);
    expect($examen->date_examen->format('Y-m-d'))->toBe('2026-11-15');
    expect($examen->date_limite_saisie->format('Y-m-d'))->toBe('2026-11-22');
});

test('an administrateur can create an examen mensuel for the système maternelle', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $response = $this->actingAs($admin)->post(route('academique.examens.store'), [
        'systeme' => 'maternelle',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ]);

    $response->assertRedirect();
    $examen = Examen::query()->firstOrFail();
    expect($examen->annee_academique_id)->toBe($anneeActive->id);
    expect($examen->systeme)->toBe(SystemeScolaire::Maternelle);
    expect($examen->type)->toBe(TypeEvaluation::EvaluationMensuelle);
});

test('choosing système secondaire creates no examen and shows an unavailable message', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create(['est_active' => true]);

    $response = $this->actingAs($admin)->post(route('academique.examens.store'), [
        'systeme' => 'secondaire',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast');
    expect(session('toast'))->toContain('en cours de développement');
    expect(Examen::query()->count())->toBe(0);
});

test('a non-administrateur cannot create an examen', function () {
    $agentScolarite = User::factory()->agentScolarite()->create();
    AnneeAcademique::factory()->create(['est_active' => true]);

    $response = $this->actingAs($agentScolarite)->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ]);

    $response->assertForbidden();
    expect(Examen::query()->count())->toBe(0);
});

test('the date limite de saisie must be on or after the date d’examen', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $response = $this->actingAs($admin)->from('/')->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-10',
    ]);

    $response->assertSessionHasErrors('date_limite_saisie');
    expect(Examen::query()->count())->toBe(0);
});

test('examen dates must fall within the active année académique’s window', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $response = $this->actingAs($admin)->from('/')->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2027-09-01',
        'date_limite_saisie' => '2027-09-08',
    ]);

    $response->assertSessionHasErrors('date_examen');
    expect(Examen::query()->count())->toBe(0);
});

test('creating a primaire examen is blocked when no année académique is active', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->from('/')->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ]);

    $response->assertSessionHasErrors('systeme');
    expect(Examen::query()->count())->toBe(0);
});
