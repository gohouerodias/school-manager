<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\DomaineEvaluation;
use App\Models\Matiere;
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

test('matières cannot be added to a maternelle niveau, with a message pointing to the domaines', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $maternelle = Niveau::factory()->maternelle()->create(['libelle' => 'Maternelle Test']);
    $matiere = Matiere::factory()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.niveau-matieres.store', $anneeAcademique), [
        'niveau_id' => $maternelle->id,
        'matieres' => [['matiere_id' => $matiere->id, 'coefficient' => 1]],
    ]);

    $response->assertSessionHasErrors('niveau_id');
    expect(session('errors')->first('niveau_id'))->toContain('Ajouter des domaines (maternelle)');
    $this->assertDatabaseMissing('niveau_matiere', ['niveau_id' => $maternelle->id]);
});

test('domaines cannot be added to a non-maternelle niveau', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $primaire = Niveau::factory()->primaire()->create();
    $domaine = DomaineEvaluation::factory()->create();

    $this->actingAs($admin)->post(route('academique.annees.niveau-domaines.store', $anneeAcademique), [
        'niveau_id' => $primaire->id,
        'domaines' => [$domaine->id],
    ])->assertSessionHasErrors('niveau_id');

    $this->assertDatabaseMissing('niveau_domaine', ['niveau_id' => $primaire->id]);
});

test('once a domaine is in its niveau programme, an enseignant can be affected to a maternelle classe', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $maternelle = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => $maternelle->id]);
    $domaine = DomaineEvaluation::factory()->create();

    $this->actingAs($admin)->post(route('academique.annees.niveau-domaines.store', $anneeAcademique), [
        'niveau_id' => $maternelle->id,
        'domaines' => [$domaine->id],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('affectations_enseignant', ['classe_id' => $classe->id, 'enseignant_id' => $enseignant->id]);
});

test('the matière panel only offers non-maternelle niveaux and the domaine panel only maternelle ones', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $maternelle = Niveau::factory()->maternelle()->create(['libelle' => 'Maternelle Zeta']);
    $primaire = Niveau::factory()->primaire()->create(['libelle' => 'CE Zeta']);

    $html = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique))->assertOk()->getContent();

    $panneauMatiere = substr($html, strpos($html, 'id="new-niveau-matiere-niveau"'), 3000);
    $panneauDomaine = substr($html, strpos($html, 'id="new-niveau-domaine-niveau"'), 3000);
    $panneauMatiere = substr($panneauMatiere, 0, strpos($panneauMatiere, '</select>'));
    $panneauDomaine = substr($panneauDomaine, 0, strpos($panneauDomaine, '</select>'));

    expect($panneauMatiere)->toContain('CE Zeta')->not->toContain('Maternelle Zeta')
        ->and($panneauDomaine)->toContain('Maternelle Zeta')->not->toContain('CE Zeta');
});
