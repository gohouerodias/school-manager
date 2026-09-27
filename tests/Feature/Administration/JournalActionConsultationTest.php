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

    JournalAction::factory()->create(['user_id' => $autre->id, 'action' => 'connexion', 'details' => 'Ligne attendue dans le résultat filtré']);
    JournalAction::factory()->create(['user_id' => $admin->id, 'action' => 'creation_eleve', 'details' => 'Ligne qui ne doit pas apparaître']);

    $response = $this->actingAs($admin)->get(route('administration.journal.index', ['user_id' => $autre->id]));

    $response->assertOk();
    // "creation_eleve" apparaît quand même dans le <select> du filtre
    // Action (qui liste toujours toutes les actions du système, pas
    // seulement celles du résultat filtré) — on vérifie donc sur le detail
    // de chaque ligne, propre à chaque entrée, plutôt que sur le nom de
    // l'action.
    $response->assertSee('Ligne attendue dans le résultat filtré');
    $response->assertDontSee('Ligne qui ne doit pas apparaître');
});

test('filtering by user with empty action/date fields in the query string does not error', function () {
    // Reproduit exactement l'URL produite en cliquant "Filtrer" en ne
    // choisissant qu'un utilisateur : action/date_debut/date_fin restent
    // présents dans le querystring mais vides — ne doit jamais atteindre
    // whereDate() avec une valeur vide (voir le commentaire dans
    // JournalActionController::index()).
    $admin = User::factory()->administrateur()->create();
    JournalAction::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->get(route('administration.journal.index', [
        'user_id' => $admin->id,
        'action' => '',
        'date_debut' => '',
        'date_fin' => '',
    ]));

    $response->assertOk();
});

test('a non-administrator cannot access the journal des actions screen', function () {
    $enseignant = User::factory()->enseignant()->create();

    $this->actingAs($enseignant)
        ->get(route('administration.journal.index'))
        ->assertForbidden();
});
