<?php

use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\User;

test('the examens tab shows a completion rate and moyenne chart when notes and bulletins exist', function () {
    $admin = User::factory()->administrateur()->create();
    $annee = AnneeAcademique::factory()->create();
    $classe = Classe::factory()->create(['annee_academique_id' => $annee->id, 'niveau_id' => Niveau::factory()->primaire()]);
    $classeMatiere = ClasseMatiere::factory()->create(['classe_id' => $classe->id]);
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['classe_id' => $classe->id, 'eleve_id' => $eleve->id]);
    $examen = Examen::factory()->create(['annee_academique_id' => $annee->id, 'date_examen' => '2025-11-15']);

    // 1 seule matière au programme, 1 seule inscription : 1 note attendue.
    Note::factory()->create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id, 'examen_id' => $examen->id]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'examen_id' => $examen->id, 'moyenne_generale' => 14]);

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $annee));

    $response->assertOk();
    $response->assertSee('data-stats=', false);

    $content = $response->getContent();
    preg_match('/data-stats="([^"]+)"/', $content, $matches);
    $donnees = json_decode(html_entity_decode($matches[1]), true);

    expect($donnees)->toHaveCount(1);
    expect($donnees[0]['taux_completion'])->toEqual(100.0);
    expect($donnees[0]['moyenne'])->toEqual(14.0);
});

test('the chart is not shown when the année has no examen yet', function () {
    $admin = User::factory()->administrateur()->create();
    $annee = AnneeAcademique::factory()->create();

    $response = $this->actingAs($admin)->get(route('academique.annees.show', $annee));

    $response->assertOk();
    $response->assertDontSee('id="chart-examens-statistiques"', false);
});
