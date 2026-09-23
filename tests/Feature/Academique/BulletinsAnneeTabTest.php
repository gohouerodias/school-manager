<?php

use App\Enums\DecisionAnnuelle;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;

test('the année académique fiche shows a "Gérer les bulletins" link per classe when the année is active', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => true]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique));

    $response->assertOk();
    $response->assertSee('data-tab-btn="bulletins"', false);
    $response->assertSee(route('eleves.bulletins.index', ['classe_id' => $classe->id]), false);
    $response->assertSee('Gérer les bulletins');
});

test('the année académique fiche shows each apprenant’s already-computed bulletin annuel when the année is not active', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => false]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);
    $eleve = Eleve::factory()->create(['nom' => 'Dossou', 'prenom' => 'Marcel']);
    $inscription = Inscription::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_id' => $classe->id,
        'moyenne_annuelle' => 14.5,
        'decision' => DecisionAnnuelle::Admis,
    ]);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique));

    $response->assertOk();
    $response->assertDontSee('Gérer les bulletins');
    $response->assertSee("n'est pas active", false);
    $response->assertSee('Dossou Marcel');
    $response->assertSee('14.50/20');
    $response->assertSee('Admis');
    $response->assertSee(route('eleves.bulletins.annuel.apercu', ['classe' => $classe, 'inscription' => $inscription]), false);
    $response->assertSee(route('eleves.bulletins.annuel.apercu.telecharger', ['classe' => $classe, 'inscription' => $inscription]), false);
});

test('an apprenant with no moyenne annuelle yet is shown as such, not as 0', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => false]);
    $classe = Classe::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'niveau_id' => Niveau::factory()->primaire()]);
    $eleve = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id, 'moyenne_annuelle' => null, 'decision' => null]);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $anneeAcademique));

    $response->assertOk();
    $response->assertSee('<td>—</td>', false);
});
