<?php

use App\Models\Classe;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\ParametreSysteme;
use App\Models\User;

test('an administrateur can create a niveau, appended in last position', function () {
    $admin = User::factory()->administrateur()->create();
    $ordreMax = (int) (Niveau::query()->max('ordre') ?? 0);

    $response = $this->actingAs($admin)->post(route('academique.niveaux.store'), [
        'libelle' => 'CP',
        'cycle' => 'primaire',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveaux', ['libelle' => 'CP', 'ordre' => $ordreMax + 1, 'cycle' => 'primaire']);
});

test('a non-administrateur cannot create a niveau', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->post(route('academique.niveaux.store'), [
        'libelle' => 'CP',
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
        'cycle' => 'primaire',
    ]);

    $response->assertSessionHasErrors('libelle');
});

test('an administrateur can move a niveau up, swapping ordre with its predecessor', function () {
    $admin = User::factory()->administrateur()->create();
    $premier = Niveau::factory()->create(['ordre' => 21]);
    $second = Niveau::factory()->create(['ordre' => 22]);

    $response = $this->actingAs($admin)->post(route('academique.niveaux.monter', $second));

    $response->assertRedirect();
    expect($second->fresh()->ordre)->toBe(21);
    expect($premier->fresh()->ordre)->toBe(22);
});

test('an administrateur can move a niveau down, swapping ordre with its successor', function () {
    $admin = User::factory()->administrateur()->create();
    $premier = Niveau::factory()->create(['ordre' => 21]);
    $second = Niveau::factory()->create(['ordre' => 22]);

    $response = $this->actingAs($admin)->post(route('academique.niveaux.descendre', $premier));

    $response->assertRedirect();
    expect($premier->fresh()->ordre)->toBe(22);
    expect($second->fresh()->ordre)->toBe(21);
});

test('moving the first niveau up does nothing', function () {
    $admin = User::factory()->administrateur()->create();
    $premier = Niveau::factory()->create(['ordre' => 21]);
    Niveau::factory()->create(['ordre' => 22]);

    $this->actingAs($admin)->post(route('academique.niveaux.monter', $premier));

    expect($premier->fresh()->ordre)->toBe(21);
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

test('updating a niveau changes its fields (its ordre is untouched)', function () {
    $admin = User::factory()->administrateur()->create();
    $niveau = Niveau::factory()->create(['libelle' => 'CI', 'ordre' => 21, 'premiere_scolarisation' => false]);

    $response = $this->actingAs($admin)->patch(route('academique.niveaux.update', $niveau), [
        'libelle' => 'CI',
        'cycle' => 'primaire',
        'premiere_scolarisation' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('niveaux', ['id' => $niveau->id, 'ordre' => 21, 'premiere_scolarisation' => true]);
});

test('an administrateur can update the seuil de passage', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);

    $response = $this->actingAs($admin)->patch(route('academique.parametres.update'), ['seuil_passage' => 12]);

    $response->assertRedirect();
    $this->assertDatabaseHas('parametres_systeme', ['seuil_passage' => 12]);
});

test('the seuil de passage must be between 0 and 20', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);

    $response = $this->actingAs($admin)->from('/')->patch(route('academique.parametres.update'), ['seuil_passage' => 25]);

    $response->assertSessionHasErrors('seuil_passage');
    $this->assertDatabaseHas('parametres_systeme', ['seuil_passage' => 10]);
});

test('a non-administrateur cannot update the seuil de passage', function () {
    $agent = User::factory()->agentScolarite()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);

    $response = $this->actingAs($agent)->patch(route('academique.parametres.update'), ['seuil_passage' => 12]);

    $response->assertForbidden();
    $this->assertDatabaseHas('parametres_systeme', ['seuil_passage' => 10]);
});
