<?php

use App\Enums\StatutEleve;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;

test('the statistiques tab shows effectifs for every année académique of the system, archived apprenants excluded', function () {
    $direction = User::factory()->direction()->create();

    $anneeA = AnneeAcademique::factory()->create(['libelle' => '2024-2025', 'date_debut' => '2024-10-01']);
    $classeA = Classe::factory()->create(['annee_academique_id' => $anneeA->id]);
    Inscription::factory()->create(['classe_id' => $classeA->id, 'eleve_id' => Eleve::factory()->create(['statut' => StatutEleve::Actif])]);
    Inscription::factory()->create(['classe_id' => $classeA->id, 'eleve_id' => Eleve::factory()->create(['statut' => StatutEleve::Actif])]);
    // Archivé : ne doit pas compter dans l'effectif de cette année.
    Inscription::factory()->create(['classe_id' => $classeA->id, 'eleve_id' => Eleve::factory()->create(['statut' => StatutEleve::Archive])]);

    $anneeB = AnneeAcademique::factory()->create(['libelle' => '2025-2026', 'date_debut' => '2025-10-01']);
    $classeB = Classe::factory()->create(['annee_academique_id' => $anneeB->id]);
    Inscription::factory()->create(['classe_id' => $classeB->id, 'eleve_id' => Eleve::factory()->create(['statut' => StatutEleve::Actif])]);

    $response = $this->actingAs($direction)->get(route('rapports.index'));

    $response->assertOk();
    $response->assertSee('data-tab-btn="statistiques"', false);

    $content = $response->getContent();
    preg_match('/data-effectifs="([^"]+)"/', $content, $matches);
    $donnees = json_decode(html_entity_decode($matches[1]), true);

    $parLibelle = collect($donnees)->keyBy('libelle');
    expect($parLibelle['2024-2025']['effectif'])->toBe(2);
    expect($parLibelle['2025-2026']['effectif'])->toBe(1);
});

test('administrateurs and agents de scolarité cannot access the rapports statistiques tab', function () {
    $admin = User::factory()->administrateur()->create();

    $this->actingAs($admin)->get(route('rapports.index'))->assertForbidden();
});
