<?php

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;

test('direction can view the eleves list but sees no mutation controls', function () {
    $direction = User::factory()->direction()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $eleve = Eleve::factory()->create(['nom' => 'Kponou', 'prenom' => 'Ines']);
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($direction)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('Kponou Ines');
    $response->assertSee('data-peut-modifier="0"', false);
    $response->assertDontSee('Nouvel apprenant');
    $response->assertDontSee('classe-assign-select', false);
    $response->assertDontSee('statut-assign-select', false);
    // Read-only fallback badges take their place.
    $response->assertSee('classe-badge none', false);
});

test('administrators and agents de scolarité still see mutation controls on the eleves list', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create();

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('data-peut-modifier="1"', false);
    $response->assertSee('Nouvel apprenant');
});

test('direction can open a fiche élève', function () {
    $direction = User::factory()->direction()->create();
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($direction)->get(route('eleves.fiche', $eleve));

    $response->assertOk();
    $response->assertJsonStructure(['identite', 'parents', 'parcours', 'documents']);
});
