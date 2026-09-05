<?php

use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\ParametreSysteme;
use App\Models\User;

function creerInscriptionAvecBulletins(array $moyennes, array $moyennesBrouillon = []): Inscription
{
    $anneeAcademique = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id]);
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    foreach ($moyennes as $moyenne) {
        Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'moyenne_generale' => $moyenne]);
    }

    foreach ($moyennesBrouillon as $moyenne) {
        Bulletin::factory()->create(['inscription_id' => $inscription->id, 'moyenne_generale' => $moyenne]);
    }

    return $inscription;
}

test('the décisions de passage page shows the computed moyenne annuelle and proposition', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = creerInscriptionAvecBulletins([12, 14, 16]);

    $response = $this->actingAs($admin)->get(route('academique.annees.decisions.index', $inscription->classe->anneeAcademique));

    $response->assertOk();
    $response->assertSee($inscription->eleve->nomComplet());
    $response->assertSee('14.00/20');
    $response->assertSee('Admis');
});

test('brouillon bulletins are excluded from the moyenne annuelle', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = creerInscriptionAvecBulletins([20], [0]);

    expect($inscription->calculerMoyenneAnnuelle())->toBe(20.0);
});

test('an administrateur can validate the automatic proposition as-is, without a motif', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = creerInscriptionAvecBulletins([14, 16]);

    $response = $this->actingAs($admin)->patch(route('academique.inscriptions.decision.update', $inscription), [
        'decision' => 'admis',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inscriptions', ['id' => $inscription->id, 'decision' => 'admis', 'moyenne_annuelle' => 15]);
});

test('overriding the proposition without a motif is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = creerInscriptionAvecBulletins([14, 16]);

    $response = $this->actingAs($admin)->from('/')->patch(route('academique.inscriptions.decision.update', $inscription), [
        'decision' => 'redouble',
    ]);

    $response->assertSessionHasErrors('motif');
    expect($inscription->fresh()->decision)->toBeNull();
});

test('overriding the proposition with a motif is accepted and logged in the journal des actions', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = creerInscriptionAvecBulletins([14, 16]);

    $response = $this->actingAs($admin)->patch(route('academique.inscriptions.decision.update', $inscription), [
        'decision' => 'redouble',
        'motif' => 'Redoublement demandé par la famille.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inscriptions', [
        'id' => $inscription->id,
        'decision' => 'redouble',
        'motif_decision' => 'Redoublement demandé par la famille.',
    ]);
    $this->assertDatabaseHas('journal_actions', [
        'user_id' => $admin->id,
        'action' => 'decision_passage',
    ]);
});

test('exclu can be chosen manually even though it is never the automatic proposition', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = creerInscriptionAvecBulletins([5, 6]);

    $response = $this->actingAs($admin)->patch(route('academique.inscriptions.decision.update', $inscription), [
        'decision' => 'exclu',
        'motif' => 'Conseil de discipline.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inscriptions', ['id' => $inscription->id, 'decision' => 'exclu']);
});

test('a non-administrateur cannot access the décisions de passage page', function () {
    $agent = User::factory()->agentScolarite()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();

    $response = $this->actingAs($agent)->get(route('academique.annees.decisions.index', $anneeAcademique));

    $response->assertForbidden();
});

test('a non-administrateur cannot record a décision de passage', function () {
    $agent = User::factory()->agentScolarite()->create();
    $inscription = creerInscriptionAvecBulletins([14]);

    $response = $this->actingAs($agent)->patch(route('academique.inscriptions.decision.update', $inscription), [
        'decision' => 'admis',
    ]);

    $response->assertForbidden();
});
