<?php

use App\Models\AnneeAcademique;
use App\Models\User;

test('the sidebar shows an "Affectation des enseignants" shortcut to the active année when one exists', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create(['est_active' => false, 'date_debut' => '2024-10-01']);
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true, 'date_debut' => '2026-10-01']);

    $response = $this->actingAs($admin)->get(route('academique.annees.index'));

    $response->assertOk();
    $response->assertSee(route('academique.annees.show', $anneeActive).'?onglet=affectations', false);
    $response->assertSee('Affectation des enseignants');
});

test('the sidebar shortcut falls back to the most recent année when none is active', function () {
    $admin = User::factory()->administrateur()->create();
    AnneeAcademique::factory()->create(['est_active' => false, 'date_debut' => '2024-10-01']);
    $anneePlusRecente = AnneeAcademique::factory()->create(['est_active' => false, 'date_debut' => '2026-10-01']);

    $response = $this->actingAs($admin)->get(route('academique.annees.index'));

    $response->assertSee(route('academique.annees.show', $anneePlusRecente).'?onglet=affectations', false);
});

test('the sidebar shortcut is disabled when no année académique exists yet', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->get(route('academique.annees.index'));

    $response->assertSee('nav-subitem disabled', false);
});

test('a non-administrateur does not see the sidebar shortcut', function () {
    $agent = User::factory()->agentScolarite()->create();
    AnneeAcademique::factory()->create();

    $response = $this->actingAs($agent)->get(route('dashboard'));

    $response->assertDontSee('Affectation des enseignants');
});
