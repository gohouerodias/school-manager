<?php

use App\Models\Classe;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;

test('an administrateur can create a niveau', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('academique.niveaux.store'), [
        'libelle' => 'CP',
        'ordre' => 2,
        'cycle' => 'primaire',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveaux', ['libelle' => 'CP', 'ordre' => 2, 'cycle' => 'primaire']);
});

test('a non-administrateur cannot create a niveau', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->post(route('academique.niveaux.store'), [
        'libelle' => 'CP',
        'ordre' => 2,
        'cycle' => 'primaire',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('niveaux', ['libelle' => 'CP']);
});

test('creating a niveau with an already-used libellé fails validation', function () {
    $admin = User::factory()->administrateur()->create();
    Niveau::factory()->create(['libelle' => 'CP']);

    $response = $this->actingAs($admin)->from(route('academique.niveaux-matieres.index'))->post(route('academique.niveaux.store'), [
        'libelle' => 'CP',
        'ordre' => 5,
        'cycle' => 'primaire',
    ]);

    $response->assertSessionHasErrors('libelle');
});

test('a niveau with classes already attached cannot be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $niveau = Niveau::factory()->create();
    Classe::factory()->create(['niveau_id' => $niveau->id]);

    $response = $this->actingAs($admin)->delete(route('academique.niveaux.destroy', $niveau));

    $response->assertRedirect();
    $this->assertDatabaseHas('niveaux', ['id' => $niveau->id]);
});

test('a niveau without classes can be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $niveau = Niveau::factory()->create();

    $response = $this->actingAs($admin)->delete(route('academique.niveaux.destroy', $niveau));

    $response->assertRedirect();
    $this->assertDatabaseMissing('niveaux', ['id' => $niveau->id]);
});

test('an administrateur can create a matière', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('academique.matieres.store'), ['nom' => 'Musique']);

    $response->assertRedirect();
    $this->assertDatabaseHas('matieres', ['nom' => 'Musique']);
});

test('creating a matière with an already-used nom fails validation', function () {
    $admin = User::factory()->administrateur()->create();
    Matiere::factory()->create(['nom' => 'Musique']);

    $response = $this->actingAs($admin)->from(route('academique.niveaux-matieres.index'))->post(route('academique.matieres.store'), ['nom' => 'Musique']);

    $response->assertSessionHasErrors('nom');
});

test('updating a niveau changes its fields', function () {
    $admin = User::factory()->administrateur()->create();
    $niveau = Niveau::factory()->create(['libelle' => 'CI', 'ordre' => 1]);

    $response = $this->actingAs($admin)->patch(route('academique.niveaux.update', $niveau), [
        'libelle' => 'CI',
        'ordre' => 3,
        'cycle' => 'primaire',
        'premiere_scolarisation' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveaux', ['id' => $niveau->id, 'ordre' => 3, 'premiere_scolarisation' => true]);
});
