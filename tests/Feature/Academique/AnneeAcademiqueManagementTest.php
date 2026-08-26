<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
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
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 4]);

    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
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
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id]);
    $matiereHorsProgramme = Matiere::factory()->create();

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiereHorsProgramme->id,
    ]);

    $response->assertSessionHasErrors('matiere_id');
});

test('a teacher can be affected to several matières of the same classe', function () {
    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id]);
    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    $classe->matieres()->attach([$francais->id => ['coefficient' => 4], $maths->id => ['coefficient' => 4]]);

    $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $francais->id,
    ]);
    $response = $this->actingAs($admin)->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $maths->id,
    ]);

    $response->assertRedirect();
    expect($enseignant->affectations()->where('classe_id', $classe->id)->count())->toBe(2);
});

test('affecting a non-enseignant user is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $agentScolarite = User::factory()->agentScolarite()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id]);
    $matiere = Matiere::factory()->create();
    $classe->matieres()->attach($matiere->id, ['coefficient' => 4]);

    $response = $this->actingAs($admin)->from(route('academique.annees.show', $anneeAcademique))->post(route('academique.annees.affectations.store', $anneeAcademique), [
        'enseignant_id' => $agentScolarite->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
    ]);

    $response->assertSessionHasErrors('enseignant_id');
});
