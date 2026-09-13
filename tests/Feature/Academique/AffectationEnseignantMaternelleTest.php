<?php

use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\DomaineEvaluation;
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

test('affecting a new teacher to a maternelle classe replaces the previous one', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->domaines()->attach(DomaineEvaluation::factory()->create()->id);
    $ancien = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $ancien->id,
        'classe_id' => $classe->id,
        'matiere_id' => null,
        'annee_academique_id' => $anneeAcademique->id,
        'est_professeur_principal' => true,
    ]);
    $nouveau = User::factory()->enseignant()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $nouveau->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertRedirect();
    expect(AffectationEnseignant::where('classe_id', $classe->id)->count())->toBe(1);
    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $nouveau->id]);
    $this->assertDatabaseMissing('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $ancien->id]);
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
