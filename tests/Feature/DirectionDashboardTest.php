<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\User;

test('direction sees a summary dashboard with quick links to éleves and rapports', function () {
    $direction = User::factory()->direction()->create();
    $annee = AnneeAcademique::factory()->create(['est_active' => true, 'libelle' => '2025-2026']);
    Classe::factory()->count(2)->create(['annee_academique_id' => $annee->id]);
    Eleve::factory()->count(3)->create();

    $response = $this->actingAs($direction)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('2025-2026');
    $response->assertSee(route('eleves.index'), false);
    $response->assertSee(route('rapports.index'), false);
});

test('the sidebar shows Direction a working link to Rapports and to the éleves list, but not Académique', function () {
    $direction = User::factory()->direction()->create();

    $response = $this->actingAs($direction)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(route('rapports.index'), false);
    $response->assertSee(route('eleves.index'), false);
    $response->assertDontSee(route('academique.annees.index'), false);
});
