<?php

use App\Models\ChampFormation;
use App\Models\Matiere;
use App\Models\User;

test('an administrateur can create champs de formation, placed in creation order', function () {
    $admin = User::factory()->administrateur()->create();

    $this->actingAs($admin)->post(route('academique.champs-formation.store'), ['nom' => 'Français'])->assertRedirect();
    $this->actingAs($admin)->post(route('academique.champs-formation.store'), ['nom' => 'EA'])->assertRedirect();

    $francais = ChampFormation::where('nom', 'Français')->firstOrFail();
    $ea = ChampFormation::where('nom', 'EA')->firstOrFail();
    expect($ea->ordre)->toBeGreaterThan($francais->ordre);
});

test('a non-administrateur cannot create a champ de formation', function () {
    $agent = User::factory()->agentScolarite()->create();

    $this->actingAs($agent)->post(route('academique.champs-formation.store'), ['nom' => 'Français'])->assertForbidden();

    $this->assertDatabaseMissing('champs_formation', ['nom' => 'Français']);
});

test('creating a champ de formation with an already-used nom fails validation', function () {
    $admin = User::factory()->administrateur()->create();
    ChampFormation::factory()->create(['nom' => 'Français']);

    $this->actingAs($admin)->from(route('academique.niveaux-matieres.index'))
        ->post(route('academique.champs-formation.store'), ['nom' => 'Français'])
        ->assertSessionHasErrors('nom');
});

test('an administrateur can rename a champ de formation', function () {
    $admin = User::factory()->administrateur()->create();
    $champ = ChampFormation::factory()->create(['nom' => 'Francais']);

    $this->actingAs($admin)->patch(route('academique.champs-formation.update', $champ), ['nom' => 'Français'])->assertRedirect();

    $this->assertDatabaseHas('champs_formation', ['id' => $champ->id, 'nom' => 'Français']);
});

test('an administrateur can assign a matière to a champ de formation and remove it', function () {
    $admin = User::factory()->administrateur()->create();
    $champ = ChampFormation::factory()->create();
    $matiere = Matiere::factory()->create(['nom' => 'Orthographe']);

    $this->actingAs($admin)->patch(route('academique.matieres.update', $matiere), [
        'nom' => 'Orthographe',
        'champ_formation_id' => $champ->id,
    ])->assertRedirect();
    expect($matiere->fresh()->champ_formation_id)->toBe($champ->id);

    $this->actingAs($admin)->patch(route('academique.matieres.update', $matiere), [
        'nom' => 'Orthographe',
        'champ_formation_id' => '',
    ])->assertRedirect();
    expect($matiere->fresh()->champ_formation_id)->toBeNull();
});

test('deleting a champ de formation keeps its matières, now unclassified', function () {
    $admin = User::factory()->administrateur()->create();
    $champ = ChampFormation::factory()->create();
    $matiere = Matiere::factory()->create(['champ_formation_id' => $champ->id]);

    $this->actingAs($admin)->delete(route('academique.champs-formation.destroy', $champ))->assertRedirect();

    $this->assertDatabaseMissing('champs_formation', ['id' => $champ->id]);
    expect($matiere->fresh()->champ_formation_id)->toBeNull();
});
