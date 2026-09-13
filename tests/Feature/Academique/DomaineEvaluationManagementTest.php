<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\DomaineEvaluation;
use App\Models\Niveau;
use App\Models\NiveauDomaine;
use App\Models\User;

test('an administrateur can create a domaine d’évaluation', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('academique.domaines.store'), ['nom' => 'Langage']);

    $response->assertRedirect();
    $this->assertDatabaseHas('domaines_evaluation', ['nom' => 'Langage']);
});

test('a non-administrateur cannot create a domaine d’évaluation', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->post(route('academique.domaines.store'), ['nom' => 'Langage']);

    $response->assertForbidden();
    $this->assertDatabaseMissing('domaines_evaluation', ['nom' => 'Langage']);
});

test('creating a domaine with an already-used nom fails validation', function () {
    $admin = User::factory()->administrateur()->create();
    DomaineEvaluation::factory()->create(['nom' => 'Langage']);

    $response = $this->actingAs($admin)->from(route('academique.niveaux-matieres.index'))->post(route('academique.domaines.store'), ['nom' => 'Langage']);

    $response->assertSessionHasErrors('nom');
});

test('an administrateur can update a domaine d’évaluation', function () {
    $admin = User::factory()->administrateur()->create();
    $domaine = DomaineEvaluation::factory()->create(['nom' => 'Langage']);

    $response = $this->actingAs($admin)->patch(route('academique.domaines.update', $domaine), ['nom' => 'Pré-Lecture']);

    $response->assertRedirect();
    $this->assertDatabaseHas('domaines_evaluation', ['id' => $domaine->id, 'nom' => 'Pré-Lecture']);
});

test('a domaine already used in a niveau programme cannot be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $domaine = DomaineEvaluation::factory()->create();
    NiveauDomaine::factory()->create(['domaine_evaluation_id' => $domaine->id]);

    $response = $this->actingAs($admin)->delete(route('academique.domaines.destroy', $domaine));

    $response->assertRedirect();
    $this->assertDatabaseHas('domaines_evaluation', ['id' => $domaine->id]);
});

test('a domaine already used in a classe cannot be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $domaine = DomaineEvaluation::factory()->create();
    ClasseDomaine::factory()->create(['domaine_evaluation_id' => $domaine->id]);

    $response = $this->actingAs($admin)->delete(route('academique.domaines.destroy', $domaine));

    $response->assertRedirect();
    $this->assertDatabaseHas('domaines_evaluation', ['id' => $domaine->id]);
});

test('an unused domaine can be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $domaine = DomaineEvaluation::factory()->create();

    $response = $this->actingAs($admin)->delete(route('academique.domaines.destroy', $domaine));

    $response->assertRedirect();
    $this->assertDatabaseMissing('domaines_evaluation', ['id' => $domaine->id]);
});

test('an administrateur can add a domaine to a niveau’s programme for an année', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $domaine = DomaineEvaluation::factory()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.niveau-domaines.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'domaines' => [$domaine->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveau_domaine', [
        'niveau_id' => $niveau->id,
        'domaine_evaluation_id' => $domaine->id,
        'annee_academique_id' => $anneeAcademique->id,
    ]);
});

test('an administrateur can add several domaines to a niveau’s programme at once', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $langage = DomaineEvaluation::factory()->create();
    $preLecture = DomaineEvaluation::factory()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.niveau-domaines.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'domaines' => [$langage->id, $preLecture->id],
    ]);

    $response->assertRedirect();
    expect($niveau->domainesPour($anneeAcademique))->toHaveCount(2);
});

test('adding the same domaine twice to the same niveau/année is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $domaine = DomaineEvaluation::factory()->create();
    NiveauDomaine::create(['niveau_id' => $niveau->id, 'domaine_evaluation_id' => $domaine->id, 'annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.niveau-domaines.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'domaines' => [$domaine->id],
    ]);

    $response->assertSessionHasErrors('domaines');
});

test('an administrateur can remove a domaine from a niveau’s programme', function () {
    $admin = User::factory()->administrateur()->create();
    $niveauDomaine = NiveauDomaine::factory()->create();

    $response = $this->actingAs($admin)->delete(route('academique.niveau-domaines.destroy', $niveauDomaine));

    $response->assertRedirect();
    $this->assertDatabaseMissing('niveau_domaine', ['id' => $niveauDomaine->id]);
});

test('adding a domaine to a niveau’s programme immediately reaches classes already created for it', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $domaine = DomaineEvaluation::factory()->create();

    expect($classe->domaines()->count())->toBe(0);

    $this->actingAs($admin)->post(route('academique.annees.niveau-domaines.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'domaines' => [$domaine->id],
    ]);

    expect($classe->domaines()->count())->toBe(1);
    $this->assertDatabaseHas('classe_domaine', ['classe_id' => $classe->id, 'domaine_evaluation_id' => $domaine->id]);
});

test('adding a classe to a maternelle niveau copies its programme into classe_domaine', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $langage = DomaineEvaluation::factory()->create(['nom' => 'Langage']);
    $preLecture = DomaineEvaluation::factory()->create(['nom' => 'Pré-Lecture']);

    NiveauDomaine::create(['niveau_id' => $niveau->id, 'domaine_evaluation_id' => $langage->id, 'annee_academique_id' => $anneeAcademique->id]);
    NiveauDomaine::create(['niveau_id' => $niveau->id, 'domaine_evaluation_id' => $preLecture->id, 'annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->post(route('academique.annees.classes.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'lettre' => 'A',
    ]);

    $response->assertRedirect();
    $classe = Classe::where('annee_academique_id', $anneeAcademique->id)->where('niveau_id', $niveau->id)->firstOrFail();
    expect($classe->domaines()->count())->toBe(2);
    $this->assertDatabaseHas('classe_domaine', ['classe_id' => $classe->id, 'domaine_evaluation_id' => $langage->id]);
});

test('modifying a maternelle classe resynchronizes its domaines from the niveau', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id, 'nom' => 'Maternelle 1 A']);
    $ancienDomaine = DomaineEvaluation::factory()->create();
    $classe->domaines()->attach($ancienDomaine->id);
    $nouveauDomaine = DomaineEvaluation::factory()->create();
    NiveauDomaine::create(['niveau_id' => $niveau->id, 'domaine_evaluation_id' => $nouveauDomaine->id, 'annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->patch(route('academique.classes.update', $classe), ['lettre' => 'B']);

    $response->assertRedirect();
    $classe->refresh();
    expect($classe->domaines()->pluck('domaines_evaluation.id')->all())->toBe([$nouveauDomaine->id]);
});
