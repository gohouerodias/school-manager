<?php

use App\Models\AnneeAcademique;
use App\Models\Examen;
use App\Models\User;

test('the année académique fiche no longer has an Examens tab nor its statistics chart', function () {
    $admin = User::factory()->administrateur()->create();
    $annee = AnneeAcademique::factory()->create(['est_active' => true]);
    Examen::factory()->create(['annee_academique_id' => $annee->id]);

    $this->actingAs($admin)->get(route('academique.annees.show', $annee))
        ->assertOk()
        ->assertDontSee('data-tab-btn="examens"', false)
        ->assertDontSee('chart-examens-statistiques', false)
        ->assertSee('data-tab-btn="affectations"', false);
});
