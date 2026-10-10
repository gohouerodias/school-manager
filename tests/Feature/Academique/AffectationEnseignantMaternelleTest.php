<?php

use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\DomaineEvaluation;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;

test('affecting a teacher to a maternelle classe creates a single matiere-less row and marks them principal', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->domaines()->attach(DomaineEvaluation::factory()->create()->id);
    $enseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('affectations_enseignant', [
        'classe_id' => $classe->id,
        'enseignant_id' => $enseignant->id,
        'matiere_id' => null,
        'est_professeur_principal' => true,
    ]);
    expect(AffectationEnseignant::where('classe_id', $classe->id)->count())->toBe(1);
});

test('affecting a second teacher to a maternelle classe adds them alongside the first, without removing them', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->domaines()->attach(DomaineEvaluation::factory()->create()->id);
    $premier = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $premier->id,
        'classe_id' => $classe->id,
        'matiere_id' => null,
        'annee_academique_id' => $anneeAcademique->id,
        'est_professeur_principal' => true,
    ]);
    $second = User::factory()->enseignant()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $second->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertRedirect();
    expect(AffectationEnseignant::where('classe_id', $classe->id)->count())->toBe(2);
    // Le premier enseignant reste titulaire, le second est simple membre.
    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $premier->id, 'est_professeur_principal' => true]);
    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $second->id, 'est_professeur_principal' => false]);
});

test('the first teacher affected to an empty primaire classe becomes titulaire automatically', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['niveau_id' => Niveau::factory()->primaire(), 'annee_academique_id' => $anneeAcademique->id]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 1]);
    $enseignant = User::factory()->enseignant()->create();

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);

    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $enseignant->id, 'est_professeur_principal' => true]);
});

test('affecting the same teacher twice to a maternelle/primaire classe is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->domaines()->attach(DomaineEvaluation::factory()->create()->id);
    $enseignant = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => null,
        'annee_academique_id' => $anneeAcademique->id,
        'est_professeur_principal' => true,
    ]);

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertSessionHasErrors('enseignant_id');
    expect(AffectationEnseignant::where('classe_id', $classe->id)->count())->toBe(1);
});

test('the titulaire badge can be moved to another teacher already affected to a maternelle/primaire classe', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->domaines()->attach(DomaineEvaluation::factory()->create()->id);
    $premier = User::factory()->enseignant()->create();
    $second = User::factory()->enseignant()->create();
    AffectationEnseignant::create(['enseignant_id' => $premier->id, 'classe_id' => $classe->id, 'matiere_id' => null, 'annee_academique_id' => $anneeAcademique->id, 'est_professeur_principal' => true]);
    AffectationEnseignant::create(['enseignant_id' => $second->id, 'classe_id' => $classe->id, 'matiere_id' => null, 'annee_academique_id' => $anneeAcademique->id, 'est_professeur_principal' => false]);

    $response = $this->actingAs($admin)->patch(route('academique.classes.titulaire.update', $classe), [
        'enseignant_id' => $second->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $second->id, 'est_professeur_principal' => true]);
    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $premier->id, 'est_professeur_principal' => false]);
});

test('the titulaire of a maternelle/primaire classe cannot be removed while another teacher remains', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->domaines()->attach(DomaineEvaluation::factory()->create()->id);
    $titulaire = User::factory()->enseignant()->create();
    $autre = User::factory()->enseignant()->create();
    AffectationEnseignant::create(['enseignant_id' => $titulaire->id, 'classe_id' => $classe->id, 'matiere_id' => null, 'annee_academique_id' => $anneeAcademique->id, 'est_professeur_principal' => true]);
    AffectationEnseignant::create(['enseignant_id' => $autre->id, 'classe_id' => $classe->id, 'matiere_id' => null, 'annee_academique_id' => $anneeAcademique->id, 'est_professeur_principal' => false]);

    $this->actingAs($admin)->delete(route('academique.classes.enseignants.destroy', [$classe, $titulaire]));

    expect(AffectationEnseignant::where('classe_id', $classe->id)->where('enseignant_id', $titulaire->id)->exists())->toBeTrue();
});

test('affecting a teacher to a maternelle classe without any domaine programme is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $enseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertSessionHasErrors('classe_id');
    $this->assertDatabaseMissing('affectations_enseignant', ['classe_id' => $classe->id]);
});
