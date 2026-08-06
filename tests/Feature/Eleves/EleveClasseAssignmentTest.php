<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;

test('enseignants and direction cannot change an élève\'s classe', function () {
    $enseignant = User::factory()->enseignant()->create();
    $direction = User::factory()->direction()->create();
    $eleve = Eleve::factory()->create();
    $classe = Classe::factory()->create();

    $this->actingAs($enseignant)->patch(route('eleves.classe.update', $eleve), ['classe_id' => $classe->id])->assertForbidden();
    $this->actingAs($direction)->patch(route('eleves.classe.update', $eleve), ['classe_id' => $classe->id])->assertForbidden();
});

test('assigning a classe to an élève with no current inscription creates one for the active année', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($admin)->patch(route('eleves.classe.update', $eleve), ['classe_id' => $classe->id]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);
    expect($eleve->inscriptions()->count())->toBe(1);
});

test('changing an élève\'s classe updates the existing active-année inscription instead of duplicating it', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $ancienneClasse = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $nouvelleClasse = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $ancienneClasse->id]);

    $response = $this->actingAs($admin)->patch(route('eleves.classe.update', $eleve), ['classe_id' => $nouvelleClasse->id]);

    $response->assertRedirect();
    expect($eleve->inscriptions()->count())->toBe(1);
    expect($inscription->fresh()->classe_id)->toBe($nouvelleClasse->id);
});

test('changing an élève\'s classe for the active année never overwrites a past année\'s historical inscription', function () {
    $admin = User::factory()->administrateur()->create();
    $anneePassee = AnneeAcademique::factory()->create(['est_active' => false]);
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classePassee = Classe::factory()->create(['annee_academique_id' => $anneePassee->id]);
    $classeActive = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $eleve = Eleve::factory()->create();
    $inscriptionPassee = Inscription::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_id' => $classePassee->id,
        'date_inscription' => now()->subYear(),
    ]);

    $response = $this->actingAs($admin)->patch(route('eleves.classe.update', $eleve), ['classe_id' => $classeActive->id]);

    $response->assertRedirect();
    expect($eleve->inscriptions()->count())->toBe(2);
    expect($inscriptionPassee->fresh()->classe_id)->toBe($classePassee->id);
    $this->assertDatabaseHas('inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classeActive->id]);
});

test('setting the classe to "Sans classe" removes the élève\'s active-année inscription', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $eleve = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($admin)->patch(route('eleves.classe.update', $eleve), ['classe_id' => null]);

    $response->assertRedirect();
    expect($eleve->inscriptions()->count())->toBe(0);
});

test('assigning a classe from a non-active année is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $anneePassee = AnneeAcademique::factory()->create(['est_active' => false]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneePassee->id]);
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($admin)
        ->from(route('eleves.index'))
        ->patch(route('eleves.classe.update', $eleve), ['classe_id' => $classe->id]);

    $response->assertSessionHasErrors('classe_id');
    expect($eleve->inscriptions()->count())->toBe(0);
});

test('the éleve list renders the classe column as a select pre-selected on the active-année inscription', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeActive->id, 'nom' => 'A']);
    $eleve = Eleve::factory()->create(['nom' => 'Selectionne']);
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('classe-assign-select', false);
    $response->assertSee('data-update-url="'.route('eleves.classe.update', $eleve).'"', false);
    // Regex rather than an exact-HTML assertSee: the @selected() Blade
    // directive's literal template whitespace around it is an
    // implementation detail (see the classe-filter regression test above
    // this file's sibling test for the exact bug that came from asserting
    // on it too literally), not something worth pinning down byte-for-byte.
    expect($response->getContent())->toMatch(
        '/<option value="'.$classe->id.'"[^>]*\bselected\b[^>]*>/'
    );
});
