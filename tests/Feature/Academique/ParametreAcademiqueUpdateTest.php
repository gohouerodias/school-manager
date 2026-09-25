<?php

use App\Models\ParametreSysteme;
use App\Models\User;

test('an administrator can update the data retention duration', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['duree_conservation_donnees' => 60]);

    $response = $this->actingAs($admin)->patch(route('academique.parametres.update'), [
        'seuil_passage' => 10,
        'duree_conservation_donnees' => 24,
    ]);

    $response->assertRedirect();
    expect(ParametreSysteme::query()->value('duree_conservation_donnees'))->toBe(24);
});

test('the data retention duration is required and must be a non-negative integer', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create();

    $response = $this->actingAs($admin)->patch(route('academique.parametres.update'), [
        'seuil_passage' => 10,
        'duree_conservation_donnees' => -1,
    ]);

    $response->assertSessionHasErrors('duree_conservation_donnees');
});
