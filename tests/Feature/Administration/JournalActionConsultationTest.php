<?php

use App\Models\JournalAction;
use App\Models\User;

test('an administrator can view the journal des actions screen', function () {
    $admin = User::factory()->administrateur()->create();
    $autre = User::factory()->enseignant()->create();

    JournalAction::factory()->create(['user_id' => $autre->id, 'action' => 'connexion']);
    JournalAction::factory()->create(['user_id' => $admin->id, 'action' => 'creation_eleve']);

    $response = $this->actingAs($admin)->get(route('administration.journal.index'));

    $response->assertOk();
    $response->assertSee('connexion');
    $response->assertSee('creation_eleve');
});

test('the journal can be filtered by user and action', function () {
    $admin = User::factory()->administrateur()->create();
    $autre = User::factory()->enseignant()->create();

    JournalAction::factory()->create(['user_id' => $autre->id, 'action' => 'connexion']);
    JournalAction::factory()->create(['user_id' => $admin->id, 'action' => 'creation_eleve']);

    $response = $this->actingAs($admin)->get(route('administration.journal.index', ['user_id' => $autre->id]));

    $response->assertOk();
    $response->assertSee('connexion');
    $response->assertDontSee('creation_eleve');
});

test('a non-administrator cannot access the journal des actions screen', function () {
    $enseignant = User::factory()->enseignant()->create();

    $this->actingAs($enseignant)
        ->get(route('administration.journal.index'))
        ->assertForbidden();
});
