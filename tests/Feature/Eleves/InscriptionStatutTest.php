<?php

use App\Enums\StatutInscription;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;

test('an administrateur can manually correct the statut of a parcours line', function () {
    $admin = User::factory()->administrateur()->create();
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($admin)->patch(
        route('eleves.inscriptions.statut.update', ['eleve' => $eleve, 'inscription' => $inscription]),
        ['statut' => 'transfert_entrant'],
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('inscriptions', ['id' => $inscription->id, 'statut' => 'transfert_entrant']);
});

test('an invalid statut value is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id, 'statut' => 'normal']);

    $response = $this->actingAs($admin)->from('/')->patch(
        route('eleves.inscriptions.statut.update', ['eleve' => $eleve, 'inscription' => $inscription]),
        ['statut' => 'n_importe_quoi'],
    );

    $response->assertSessionHasErrors('statut');
    expect($inscription->fresh()->statut)->toBe(StatutInscription::Normal);
});

test('an inscription belonging to a different élève cannot be edited through this route', function () {
    $admin = User::factory()->administrateur()->create();
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    $autreEleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $autreEleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($admin)->patch(
        route('eleves.inscriptions.statut.update', ['eleve' => $eleve, 'inscription' => $inscription]),
        ['statut' => 'abandon'],
    );

    $response->assertNotFound();
});

test('an agent de scolarité can also correct a statut (same rights as tuteurs/documents)', function () {
    $agent = User::factory()->agentScolarite()->create();
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($agent)->patch(
        route('eleves.inscriptions.statut.update', ['eleve' => $eleve, 'inscription' => $inscription]),
        ['statut' => 'abandon'],
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('inscriptions', ['id' => $inscription->id, 'statut' => 'abandon']);
});

test('enseignants and direction cannot correct a statut', function () {
    $enseignant = User::factory()->enseignant()->create();
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($enseignant)->patch(
        route('eleves.inscriptions.statut.update', ['eleve' => $eleve, 'inscription' => $inscription]),
        ['statut' => 'abandon'],
    );

    $response->assertForbidden();
});
