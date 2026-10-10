<?php

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\CommentaireMatiere;
use App\Models\Examen;
use App\Models\Note;
use App\Models\User;

test('the examens index lists examens from every année académique, active or past', function () {
    $admin = User::factory()->administrateur()->create();
    $anneePassee = AnneeAcademique::factory()->create(['libelle' => '2025-2026', 'est_active' => false]);
    $anneeActive = AnneeAcademique::factory()->create(['libelle' => '2026-2027', 'est_active' => true]);
    $examenPasse = Examen::factory()->create(['annee_academique_id' => $anneePassee->id]);
    $examenActif = Examen::factory()->create(['annee_academique_id' => $anneeActive->id]);

    $response = $this->actingAs($admin)->get(route('academique.examens.index'));

    $response->assertOk();
    $response->assertSee($anneePassee->libelle);
    $response->assertSee($anneeActive->libelle);
    $response->assertViewHas('examens', function ($examens) use ($examenPasse, $examenActif) {
        return $examens->pluck('id')->contains($examenPasse->id)
            && $examens->pluck('id')->contains($examenActif->id);
    });
});

test('the examens index can be filtered by année académique', function () {
    $admin = User::factory()->administrateur()->create();
    $anneePassee = AnneeAcademique::factory()->create(['libelle' => '2025-2026', 'est_active' => false]);
    $anneeActive = AnneeAcademique::factory()->create(['libelle' => '2026-2027', 'est_active' => true]);
    $examenPasse = Examen::factory()->create(['annee_academique_id' => $anneePassee->id]);
    $examenActif = Examen::factory()->create(['annee_academique_id' => $anneeActive->id]);

    $response = $this->actingAs($admin)->get(route('academique.examens.index', ['annee_academique_id' => $anneePassee->id]));

    $response->assertOk();
    $response->assertViewHas('examens', function ($examens) use ($examenPasse, $examenActif) {
        return $examens->pluck('id')->contains($examenPasse->id)
            && ! $examens->pluck('id')->contains($examenActif->id);
    });
});

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

test('an administrateur can update an examen’s dates', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create([
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);
    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeAcademique->id,
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ]);

    $response = $this->actingAs($admin)->patch(route('academique.examens.update', $examen), [
        'date_examen' => '2026-12-01',
        'date_limite_saisie' => '2026-12-08',
    ]);

    $response->assertRedirect();
    expect($examen->fresh()->date_examen->format('Y-m-d'))->toBe('2026-12-01');
    expect($examen->fresh()->date_limite_saisie->format('Y-m-d'))->toBe('2026-12-08');
});

test('updating an examen’s dates outside its année académique’s window is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create([
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);
    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeAcademique->id,
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ]);

    $response = $this->actingAs($admin)->from('/')->patch(route('academique.examens.update', $examen), [
        'date_examen' => '2027-09-01',
        'date_limite_saisie' => '2027-09-08',
    ]);

    $response->assertSessionHasErrors('date_examen');
    expect($examen->fresh()->date_examen->format('Y-m-d'))->toBe('2026-11-15');
});

test('a non-administrateur cannot update an examen', function () {
    $agentScolarite = User::factory()->agentScolarite()->create();
    $examen = Examen::factory()->create();

    $response = $this->actingAs($agentScolarite)->patch(route('academique.examens.update', $examen), [
        'date_examen' => '2026-12-01',
        'date_limite_saisie' => '2026-12-08',
    ]);

    $response->assertForbidden();
});

test('an administrateur can delete an examen, cascading to its notes, commentaires and bulletins', function () {
    $admin = User::factory()->administrateur()->create();
    $examen = Examen::factory()->create();
    $note = Note::factory()->create(['examen_id' => $examen->id]);
    $commentaire = CommentaireMatiere::factory()->create(['examen_id' => $examen->id]);
    $bulletin = Bulletin::factory()->create(['examen_id' => $examen->id]);

    $response = $this->actingAs($admin)->delete(route('academique.examens.destroy', $examen));

    $response->assertRedirect();
    $this->assertDatabaseMissing('examens', ['id' => $examen->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    $this->assertDatabaseMissing('commentaires_matiere', ['id' => $commentaire->id]);
    $this->assertDatabaseMissing('bulletins', ['id' => $bulletin->id]);
});

test('a non-administrateur cannot delete an examen', function () {
    $agentScolarite = User::factory()->agentScolarite()->create();
    $examen = Examen::factory()->create();

    $response = $this->actingAs($agentScolarite)->delete(route('academique.examens.destroy', $examen));

    $response->assertForbidden();
    $this->assertDatabaseHas('examens', ['id' => $examen->id]);
});

test('the année académique fiche shows its own examens under an Examens tab, with a "Créer" button when it is active', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => true]);
    $examen = Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique));

    $response->assertOk();
    $response->assertSee('data-tab-btn="examens"', false);
    $response->assertSee($examen->date_examen->format('d/m/Y'));
    $response->assertSee('Créer un examen');
    $response->assertSee('data-panel-open="new-examen"', false);
});

test('the année académique fiche hides the "Créer un examen" button when the année is not active, but still lists its examens', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => false]);
    $examen = Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique));

    $response->assertOk();
    $response->assertSee($examen->date_examen->format('d/m/Y'));
    $response->assertDontSee('data-panel-open="new-examen"', false);
    $response->assertSee("n'est pas active", false);
});

test('the année académique fiche never shows another année’s examens under its own Examens tab', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $autreAnnee = AnneeAcademique::factory()->create();
    Examen::factory()->create(['annee_academique_id' => $autreAnnee->id, 'date_examen' => '2030-05-15']);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique));

    $response->assertOk();
    $response->assertDontSee('15/05/2030');
});

test('the date limite de saisie can carry a time', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create(['est_active' => true, 'date_debut' => '2026-10-01', 'date_fin' => '2027-07-31']);

    $this->actingAs($admin)->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22T18:00',
    ])->assertSessionHasNoErrors();

    $examen = Examen::query()->firstOrFail();
    expect($examen->date_limite_saisie->format('Y-m-d H:i'))->toBe('2026-11-22 18:00')
        ->and($examen->dateLimiteSaisieLibelle())->toBe('22/11/2026 à 18h00');
});

test('a date limite without a time still means until the end of that day', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create(['est_active' => true, 'date_debut' => '2026-10-01', 'date_fin' => '2027-07-31']);

    $this->actingAs($admin)->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2026-11-15',
        'date_limite_saisie' => '2026-11-22',
    ])->assertSessionHasNoErrors();

    expect(Examen::query()->firstOrFail()->date_limite_saisie->format('H:i'))->toBe('23:59');
});

test('a deadline on the last day of the année, with a time, is still inside its period', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create(['est_active' => true, 'date_debut' => '2026-10-01', 'date_fin' => '2027-07-31']);

    $this->actingAs($admin)->post(route('academique.examens.store'), [
        'systeme' => 'primaire',
        'date_examen' => '2027-07-20',
        'date_limite_saisie' => '2027-07-31T17:00',
    ])->assertSessionHasNoErrors();
});

test('the saisie closes at the exact time of the deadline, not at the end of the day', function () {
    $ouvert = Examen::factory()->create(['date_limite_saisie' => now()->addHour()]);
    $ferme = Examen::factory()->create(['date_limite_saisie' => now()->subHour()]);

    expect($ouvert->delaiSaisieDepasse())->toBeFalse()
        ->and($ferme->delaiSaisieDepasse())->toBeTrue();
});

test('the « Examens » menu opens the Examens tab of the current année, so the breadcrumb leads back to it', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true, 'libelle' => '2026-2027']);

    $this->actingAs($admin)->get(route('academique.niveaux-matieres.index'))
        ->assertOk()
        ->assertSee(route('academique.annees.show', $anneeActive).'?onglet=examens', false);

    $this->actingAs($admin)->get(route('academique.annees.show', $anneeActive).'?onglet=examens')
        ->assertOk()
        ->assertSeeInOrder(['Années académiques</a>', 'breadcrumb-sep', '2026-2027'], false);
});
