<?php

use App\Enums\SystemeScolaire;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\NiveauMatiere;
use App\Models\User;

test('an administrateur can create an année académique', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.store'), [
        'libelle' => '2026-2027',
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $anneeAcademique = AnneeAcademique::where('libelle', '2026-2027')->firstOrFail();
    $response->assertRedirect(route('academique.annees.show', $anneeAcademique));
    expect($anneeAcademique->est_active)->toBeFalse();
});

test('a non-administrateur cannot create an année académique', function () {
    $agentScolarite = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agentScolarite)->post(route('academique.annees.store'), [
        'libelle' => '2026-2027',
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $response->assertForbidden();
});

test('an administrateur can update an année académique’s dates without changing its libellé', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-10-01',
        'date_fin' => '2027-07-31',
    ]);

    $response = $this->actingAs($admin)->patch(route('academique.annees.update', $anneeAcademique), [
        'date_debut' => '2026-09-15',
        'date_fin' => '2027-08-15',
    ]);

    $response->assertRedirect();
    $anneeAcademique->refresh();
    expect($anneeAcademique->libelle)->toBe('2026-2027');
    expect($anneeAcademique->date_debut->format('Y-m-d'))->toBe('2026-09-15');
    expect($anneeAcademique->date_fin->format('Y-m-d'))->toBe('2027-08-15');
});

test('updating an année académique rejects a date_fin before date_debut', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['date_debut' => '2026-10-01', 'date_fin' => '2027-07-31']);

    $response = $this->actingAs($admin)->patch(route('academique.annees.update', $anneeAcademique), [
        'date_debut' => '2026-10-01',
        'date_fin' => '2026-09-01',
    ]);

    $response->assertSessionHasErrors('date_fin');
    expect($anneeAcademique->refresh()->date_fin->format('Y-m-d'))->toBe('2027-07-31');
});

test('a non-administrateur cannot update an année académique', function () {
    $agentScolarite = User::factory()->agentScolarite()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();

    $response = $this->actingAs($agentScolarite)->patch(route('academique.annees.update', $anneeAcademique), [
        'date_debut' => $anneeAcademique->date_debut->format('Y-m-d'),
        'date_fin' => $anneeAcademique->date_fin->format('Y-m-d'),
    ]);

    $response->assertForbidden();
});

test('updating an année académique rejects a window that would exclude an existing examen', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['date_debut' => '2026-09-15', 'date_fin' => '2027-08-15']);
    Examen::factory()->create([
        'annee_academique_id' => $anneeAcademique->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => '2027-01-15',
        'date_limite_saisie' => '2027-01-31',
    ]);

    $response = $this->actingAs($admin)->patch(route('academique.annees.update', $anneeAcademique), [
        'date_debut' => '2026-09-15',
        'date_fin' => '2027-01-20',
    ]);

    $response->assertSessionHasErrors('date_fin');
    expect($anneeAcademique->refresh()->date_fin->format('Y-m-d'))->toBe('2027-08-15');
});

test('adding a classe to an année copies the niveau’s programme into classe_matiere', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create();
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);

    NiveauMatiere::create(['niveau_id' => $niveau->id, 'matiere_id' => $francais->id, 'annee_academique_id' => $anneeAcademique->id, 'coefficient' => 4]);
    NiveauMatiere::create(['niveau_id' => $niveau->id, 'matiere_id' => $maths->id, 'annee_academique_id' => $anneeAcademique->id, 'coefficient' => 4]);

    $response = $this->actingAs($admin)->post(route('academique.annees.classes.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'lettre' => 'A',
    ]);

    $response->assertRedirect();
    $classe = Classe::where('annee_academique_id', $anneeAcademique->id)->where('niveau_id', $niveau->id)->firstOrFail();
    expect($classe->nom)->toBe("{$niveau->libelle} A");
    expect($classe->matieres()->count())->toBe(2);
    $this->assertDatabaseHas('classe_matiere', ['classe_id' => $classe->id, 'matiere_id' => $francais->id, 'coefficient' => 4]);
});

test('a classe with a lettre already used in this niveau/année is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create(['libelle' => 'CM1']);
    Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id, 'nom' => 'CM1 A']);

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.classes.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'lettre' => 'A',
    ]);

    $response->assertSessionHasErrors('lettre');
});

test('modifying a classe renames it and resynchronizes its matières from the niveau', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create(['libelle' => 'CM1']);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id, 'nom' => 'CM1 A']);
    $ancienneMatiere = Matiere::factory()->create();
    $classe->matieres()->attach($ancienneMatiere->id, ['coefficient' => 99]);
    $nouvelleMatiere = Matiere::factory()->create();
    NiveauMatiere::create(['niveau_id' => $niveau->id, 'matiere_id' => $nouvelleMatiere->id, 'annee_academique_id' => $anneeAcademique->id, 'coefficient' => 4]);

    $response = $this->actingAs($admin)->patch(route('academique.classes.update', $classe), ['lettre' => 'B']);

    $response->assertRedirect();
    $classe->refresh();
    expect($classe->nom)->toBe('CM1 B');
    expect($classe->matieres()->pluck('matieres.id')->all())->toBe([$nouvelleMatiere->id]);
});

test('a classe with inscriptions cannot be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $classe = Classe::factory()->create();
    Inscription::factory()->create(['classe_id' => $classe->id]);

    $response = $this->actingAs($admin)->delete(route('academique.classes.destroy', $classe));

    $response->assertRedirect();
    $this->assertDatabaseHas('classes', ['id' => $classe->id]);
});

test('an administrateur can add a matière to a niveau’s programme for an année', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create();
    $matiere = Matiere::factory()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.niveau-matieres.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'matieres' => [
            ['matiere_id' => $matiere->id, 'coefficient' => 3],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveau_matiere', [
        'niveau_id' => $niveau->id,
        'matiere_id' => $matiere->id,
        'annee_academique_id' => $anneeAcademique->id,
        'coefficient' => 3,
    ]);
});

test('an administrateur can add several matières to a niveau’s programme at once', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create();
    $francais = Matiere::factory()->create();
    $maths = Matiere::factory()->create();

    $response = $this->actingAs($admin)->post(route('academique.annees.niveau-matieres.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'matieres' => [
            ['matiere_id' => $francais->id, 'coefficient' => 4],
            ['matiere_id' => $maths->id, 'coefficient' => 5],
        ],
    ]);

    $response->assertRedirect();
    expect($niveau->matieresPour($anneeAcademique))->toHaveCount(2);
    $this->assertDatabaseHas('niveau_matiere', ['niveau_id' => $niveau->id, 'matiere_id' => $francais->id, 'coefficient' => 4]);
    $this->assertDatabaseHas('niveau_matiere', ['niveau_id' => $niveau->id, 'matiere_id' => $maths->id, 'coefficient' => 5]);
});

test('adding the same matière twice to the same niveau/année is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create();
    $matiere = Matiere::factory()->create();
    NiveauMatiere::create(['niveau_id' => $niveau->id, 'matiere_id' => $matiere->id, 'annee_academique_id' => $anneeAcademique->id, 'coefficient' => 3]);

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.niveau-matieres.store', $anneeAcademique), [
        'niveau_id' => $niveau->id,
        'matieres' => [
            ['matiere_id' => $matiere->id, 'coefficient' => 5],
        ],
    ]);

    $response->assertSessionHasErrors('matieres');
});

test('modifying a niveau_matiere coefficient cascades to already-created classes of that niveau', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create();
    $matiere = Matiere::factory()->create();
    $niveauMatiere = NiveauMatiere::create(['niveau_id' => $niveau->id, 'matiere_id' => $matiere->id, 'annee_academique_id' => $anneeAcademique->id, 'coefficient' => 3]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $classe->matieres()->attach($matiere->id, ['coefficient' => 3]);

    $response = $this->actingAs($admin)->patch(route('academique.niveau-matieres.update', $niveauMatiere), ['coefficient' => 6]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveau_matiere', ['id' => $niveauMatiere->id, 'coefficient' => 6]);
    $this->assertDatabaseHas('classe_matiere', ['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 6]);
});

test('an administrateur can affect an enseignant to a classe for a matière in its programme', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 4]);

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$matiere->id],
        'est_professeur_principal' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('affectations_enseignant', [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
        'annee_academique_id' => $anneeAcademique->id,
        'est_professeur_principal' => true,
    ]);
});

test('affecting an enseignant to a matière not in the classe’s programme is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $matiereHorsProgramme = Matiere::factory()->create();

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$matiereHorsProgramme->id],
    ]);

    $response->assertSessionHasErrors('matiere_ids');
});

test('an administrateur can affect an enseignant to several matières of a classe in a single action', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id, $maths->id],
    ]);

    $response->assertRedirect();
    $affectations = $enseignant->affectations()->where('classe_id', $classe->id)->get();
    expect($affectations)->toHaveCount(2);
    expect($affectations->pluck('matiere_id')->sort()->values()->all())->toEqualCanonicalizing([$francais->id, $maths->id]);
});

test('submitting no matière at all for a collège classe is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);

    $response = $this->actingAs($admin)->from('/')->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [],
    ]);

    $response->assertSessionHasErrors('matiere_ids');
});

test('a teacher can be affected to more matières of the same classe in a later action', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
    ]);
    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$maths->id],
    ]);

    $response->assertRedirect();
    expect($enseignant->affectations()->where('classe_id', $classe->id)->count())->toBe(2);
});

test('re-submitting an already-assigned matière for the same enseignant is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 4]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$matiere->id],
    ]);

    $response = $this->actingAs($admin)->from('/')->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$matiere->id],
    ]);

    $response->assertSessionHasErrors('matiere_ids');
    expect($enseignant->affectations()->where('classe_id', $classe->id)->count())->toBe(1);
});

test('affecting a non-enseignant user is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $agentScolarite = User::factory()->agentScolarite()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 4]);

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $agentScolarite->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$matiere->id],
    ]);

    $response->assertSessionHasErrors('enseignant_id');
});

test('affecting an enseignant to a primaire classe assigns every matière of its programme at once', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertRedirect();
    $affectations = AffectationEnseignant::query()->where('classe_id', $classe->id)->get();
    expect($affectations)->toHaveCount(2);
    expect($affectations->pluck('matiere_id')->sort()->values()->all())->toEqualCanonicalizing([$francais->id, $maths->id]);
    expect($affectations->every(fn ($affectation) => $affectation->enseignant_id === $enseignant->id))->toBeTrue();
    expect($affectations->every(fn ($affectation) => $affectation->est_professeur_principal === true))->toBeTrue();
});

test('re-affecting a different enseignant to a primaire classe replaces the previous teacher entirely', function () {
    $admin = User::factory()->administrateur()->create();
    $ancienEnseignant = User::factory()->enseignant()->create();
    $nouvelEnseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 4]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $ancienEnseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $nouvelEnseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertRedirect();
    $affectations = AffectationEnseignant::query()->where('classe_id', $classe->id)->get();
    expect($affectations)->toHaveCount(1);
    expect($affectations->first()->enseignant_id)->toBe($nouvelEnseignant->id);
});

test('affecting an enseignant to a maternelle/primaire classe with no programme is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);

    $response = $this->actingAs($admin)->from('/')->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);

    $response->assertSessionHasErrors('classe_id');
    expect(AffectationEnseignant::query()->where('classe_id', $classe->id)->count())->toBe(0);
});

test('designating a titulaire among a collège classe’s already-assigned enseignants unsets the previous one', function () {
    $admin = User::factory()->administrateur()->create();
    $profFrancais = User::factory()->enseignant()->create();
    $profMaths = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $profFrancais->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
        'est_professeur_principal' => '1',
    ]);
    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $profMaths->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$maths->id],
    ]);

    $response = $this->actingAs($admin)->patch(route('academique.classes.titulaire.update', $classe), [
        'enseignant_id' => $profMaths->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $profMaths->id, 'classe_id' => $classe->id, 'est_professeur_principal' => true]);
    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $profFrancais->id, 'classe_id' => $classe->id, 'est_professeur_principal' => false]);
});

test('designating a titulaire not assigned to the classe is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignantExterne = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);

    $response = $this->actingAs($admin)->from('/')->patch(route('academique.classes.titulaire.update', $classe), [
        'enseignant_id' => $enseignantExterne->id,
    ]);

    $response->assertSessionHasErrors('enseignant_id');
});

test('marking a new enseignant as professeur principal at assignment time unsets the previous titulaire', function () {
    $admin = User::factory()->administrateur()->create();
    $profFrancais = User::factory()->enseignant()->create();
    $profMaths = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $profFrancais->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
        'est_professeur_principal' => '1',
    ]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $profMaths->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$maths->id],
        'est_professeur_principal' => '1',
    ]);

    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $profMaths->id, 'classe_id' => $classe->id, 'est_professeur_principal' => true]);
    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $profFrancais->id, 'classe_id' => $classe->id, 'est_professeur_principal' => false]);
});

test('removing a primaire classe’s teacher removes every matière affectation at once', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
    ]);
    $affectation = AffectationEnseignant::query()->where('classe_id', $classe->id)->first();

    $response = $this->actingAs($admin)->delete(route('academique.affectations.destroy', $affectation));

    $response->assertRedirect();
    expect(AffectationEnseignant::query()->where('classe_id', $classe->id)->count())->toBe(0);
});

test('removing an enseignant from a collège classe removes every matière they teach there at once', function () {
    $admin = User::factory()->administrateur()->create();
    $prof = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $prof->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id, $maths->id],
    ]);

    $response = $this->actingAs($admin)->delete(route('academique.classes.enseignants.destroy', [$classe, $prof]));

    $response->assertRedirect();
    expect(AffectationEnseignant::query()->where('classe_id', $classe->id)->where('enseignant_id', $prof->id)->count())->toBe(0);
});

test('removing the current titulaire is rejected while another enseignant remains assigned to the classe', function () {
    $admin = User::factory()->administrateur()->create();
    $titulaire = User::factory()->enseignant()->create();
    $autreProf = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $titulaire->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
        'est_professeur_principal' => '1',
    ]);
    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $autreProf->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$maths->id],
    ]);

    $response = $this->actingAs($admin)->delete(route('academique.classes.enseignants.destroy', [$classe, $titulaire]));

    $response->assertRedirect();
    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $titulaire->id, 'classe_id' => $classe->id]);
});

test('removing the sole enseignant of a classe (even the titulaire) succeeds', function () {
    $admin = User::factory()->administrateur()->create();
    $titulaire = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $titulaire->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
        'est_professeur_principal' => '1',
    ]);

    $response = $this->actingAs($admin)->delete(route('academique.classes.enseignants.destroy', [$classe, $titulaire]));

    $response->assertRedirect();
    expect(AffectationEnseignant::query()->where('classe_id', $classe->id)->count())->toBe(0);
});

test('a non-admin cannot remove an enseignant from a classe', function () {
    $prof = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);

    $response = $this->actingAs($prof)->delete(route('academique.classes.enseignants.destroy', [$classe, $prof]));

    $response->assertForbidden();
});

test('a matière already taught by another enseignant in the same classe cannot be given to a second one', function () {
    $admin = User::factory()->administrateur()->create();
    $profFrancais = User::factory()->enseignant()->create();
    $autreProf = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $profFrancais->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
    ]);

    $response = $this->actingAs($admin)->from('/')->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $autreProf->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id, $maths->id],
    ]);

    $response->assertSessionHasErrors('matiere_ids');
    $this->assertDatabaseMissing('affectations_enseignant', ['enseignant_id' => $autreProf->id, 'classe_id' => $classe->id]);
    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $profFrancais->id, 'classe_id' => $classe->id, 'matiere_id' => $francais->id]);
});

test('a matière freed by removing its enseignant can then be given to another one', function () {
    $admin = User::factory()->administrateur()->create();
    $profFrancais = User::factory()->enseignant()->create();
    $autreProf = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->college()]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $profFrancais->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
    ]);
    $this->actingAs($admin)->delete(route('academique.classes.enseignants.destroy', [$classe, $profFrancais]));

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $autreProf->id,
        'classe_id' => $classe->id,
        'matiere_ids' => [$francais->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('affectations_enseignant', ['enseignant_id' => $autreProf->id, 'classe_id' => $classe->id, 'matiere_id' => $francais->id]);
});
