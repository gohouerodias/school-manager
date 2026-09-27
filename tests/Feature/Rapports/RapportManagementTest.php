<?php

use App\Enums\DecisionAnnuelle;
use App\Enums\FormatRapport;
use App\Enums\StatutEleve;
use App\Enums\TypeRapport;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;

test('only direction and administrateur can access the rapports screen', function () {
    $direction = User::factory()->direction()->create();
    $admin = User::factory()->administrateur()->create();
    $agent = User::factory()->agentScolarite()->create();
    $enseignant = User::factory()->enseignant()->create();

    $this->actingAs($direction)->get(route('rapports.index'))->assertOk();
    $this->actingAs($admin)->get(route('rapports.index'))->assertOk();
    $this->actingAs($agent)->get(route('rapports.index'))->assertForbidden();
    $this->actingAs($enseignant)->get(route('rapports.index'))->assertForbidden();
});

test('the effectifs rapport counts apprenants per classe, by sexe, and those without a classe', function () {
    $direction = User::factory()->direction()->create();
    $annee = AnneeAcademique::factory()->create(['est_active' => true]);
    $niveau = Niveau::factory()->primaire()->create(['libelle' => 'CM1']);
    $classe = Classe::factory()->create(['annee_academique_id' => $annee->id, 'niveau_id' => $niveau->id, 'nom' => 'A']);

    $garcon = Eleve::factory()->create(['sexe' => 'M']);
    Inscription::factory()->create(['eleve_id' => $garcon->id, 'classe_id' => $classe->id]);
    $fille = Eleve::factory()->create(['sexe' => 'F']);
    Inscription::factory()->create(['eleve_id' => $fille->id, 'classe_id' => $classe->id]);

    // Archived élève in the same classe: must not count in the effectif.
    $archive = Eleve::factory()->create(['sexe' => 'F', 'statut' => StatutEleve::Archive]);
    Inscription::factory()->create(['eleve_id' => $archive->id, 'classe_id' => $classe->id]);

    // Actif élève with no classe at all in this année.
    Eleve::factory()->create(['statut' => StatutEleve::Actif]);

    $response = $this->actingAs($direction)->get(route('rapports.index', ['type' => 'effectifs', 'annee_academique_id' => $annee->id]));

    $response->assertOk();
    $response->assertSee('CM1');
    $response->assertSee('>2</td>', false); // effectif de la classe A
    $response->assertSee('Sans classe');
});

test('the resultats rapport computes moyenne de classe and taux admis/redouble/exclu', function () {
    $direction = User::factory()->direction()->create();
    $annee = AnneeAcademique::factory()->create(['est_active' => false]);
    $classe = Classe::factory()->create(['annee_academique_id' => $annee->id, 'niveau_id' => Niveau::factory()->primaire(), 'nom' => 'B']);

    Inscription::factory()->create(['classe_id' => $classe->id, 'moyenne_annuelle' => 15, 'decision' => DecisionAnnuelle::Admis]);
    Inscription::factory()->create(['classe_id' => $classe->id, 'moyenne_annuelle' => 9, 'decision' => DecisionAnnuelle::Redouble]);

    $response = $this->actingAs($direction)->get(route('rapports.index', ['type' => 'resultats', 'annee_academique_id' => $annee->id]));

    $response->assertOk();
    $response->assertSee('12.00/20');
    $response->assertSee('50%');
});

test('the archives rapport lists archived apprenants with their date d\'archivage and dernière classe', function () {
    $direction = User::factory()->direction()->create();
    $classe = Classe::factory()->create(['niveau_id' => Niveau::factory()->primaire()->create(['libelle' => 'CE1'])]);
    $eleve = Eleve::factory()->create([
        'nom' => 'Houngbo',
        'prenom' => 'Sylvie',
        'statut' => StatutEleve::Archive,
        'date_archivage' => '2025-06-30',
    ]);
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($direction)->get(route('rapports.index', ['type' => 'archives']));

    $response->assertOk();
    $response->assertSee('Houngbo Sylvie');
    $response->assertSee('30/06/2025');
    $response->assertSee('CE1');
});

test('exporting a rapport logs a Rapport row for the current user', function () {
    $direction = User::factory()->direction()->create();
    $annee = AnneeAcademique::factory()->create();
    Classe::factory()->create(['annee_academique_id' => $annee->id]);

    $response = $this->actingAs($direction)->get(route('rapports.export.pdf', ['type' => 'effectifs', 'annee_academique_id' => $annee->id]));

    $response->assertOk();
    $this->assertDatabaseHas('rapports', [
        'genere_par' => $direction->id,
        'type' => TypeRapport::Effectifs->value,
        'format' => FormatRapport::Pdf->value,
    ]);
});

test('exporting the archives rapport to excel does not require an année académique', function () {
    $direction = User::factory()->direction()->create();

    $response = $this->actingAs($direction)->get(route('rapports.export.excel', ['type' => 'archives']));

    $response->assertOk();
    $this->assertDatabaseHas('rapports', [
        'genere_par' => $direction->id,
        'type' => TypeRapport::Archives->value,
        'format' => FormatRapport::Excel->value,
    ]);
});

test('agents de scolarité cannot export rapports, but administrators can', function () {
    $admin = User::factory()->administrateur()->create();
    $agent = User::factory()->agentScolarite()->create();
    $annee = AnneeAcademique::factory()->create();

    $this->actingAs($admin)->get(route('rapports.export.pdf', ['type' => 'effectifs', 'annee_academique_id' => $annee->id]))->assertOk();
    $this->actingAs($agent)->get(route('rapports.export.pdf', ['type' => 'effectifs', 'annee_academique_id' => $annee->id]))->assertForbidden();
});
