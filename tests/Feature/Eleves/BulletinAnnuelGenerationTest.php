<?php

use App\Enums\CycleNiveau;
use App\Enums\DecisionAnnuelle;
use App\Enums\StatutGenerationBulletin;
use App\Enums\SystemeScolaire;
use App\Jobs\GenererBulletinsAnnuelsClasseJob;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\DemandeGenerationBulletinAnnuel;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\ParametreSysteme;
use App\Models\User;
use App\Services\BulletinGenerationService;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{classe: Classe, anneeAcademique: AnneeAcademique, inscriptions: array<int, Inscription>}
 */
function setupClasseAnnuelle(int $nombreEvaluationsPrevues = 1): array
{
    $anneeAcademique = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
        'nombre_evaluations_prevues' => $nombreEvaluationsPrevues,
    ]);
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Primaire]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id, 'nom' => 'CM2-A']);

    $eleve1 = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Emmanuel']);
    $eleve2 = Eleve::factory()->create(['nom' => 'Biaou', 'prenom' => 'Grace']);
    $inscription1 = Inscription::factory()->create(['eleve_id' => $eleve1->id, 'classe_id' => $classe->id]);
    $inscription2 = Inscription::factory()->create(['eleve_id' => $eleve2->id, 'classe_id' => $classe->id]);

    return ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => [$inscription1, $inscription2]];
}

test('the bulletins screen explains why the bulletin annuel is not yet proposed', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique] = setupClasseAnnuelle(nombreEvaluationsPrevues: 2);

    Examen::factory()->create([
        'annee_academique_id' => $anneeAcademique->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(10)->toDateString(),
        'date_limite_saisie' => now()->addDays(2)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id]));

    $response->assertOk();
    $response->assertSee('1 / 2 pour l\'instant', false);
});

test('requesting the bulletin annuel is rejected while the nombre d’évaluations threshold is not reached', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe] = setupClasseAnnuelle(nombreEvaluationsPrevues: 3);

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.annuel.demander'), ['classe_id' => $classe->id]);

    $response->assertRedirect();
    expect(DemandeGenerationBulletinAnnuel::where('classe_id', $classe->id)->exists())->toBeFalse();
});

test('requesting the bulletin annuel is rejected while at least one apprenant has no bulletin mensuel validé', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => $inscriptions] = setupClasseAnnuelle();

    Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'systeme' => SystemeScolaire::Primaire]);

    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 14]);
    // $inscriptions[1] has no Bulletin at all.

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.annuel.demander'), ['classe_id' => $classe->id]);

    $response->assertRedirect();
    expect(DemandeGenerationBulletinAnnuel::where('classe_id', $classe->id)->exists())->toBeFalse();
});

test('GenererBulletinsAnnuelsClasseJob computes the moyenne annuelle/rang and produces a downloadable ZIP', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => $inscriptions] = setupClasseAnnuelle();

    Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'systeme' => SystemeScolaire::Primaire]);

    // Deux bulletins mensuels Validés pour le premier apprenant (moyenne
    // annuelle = moyenne simple des deux, voir Inscription::
    // calculerMoyenneAnnuelle()), un seul (Brouillon, donc ignoré) pour le
    // second — qui garde tout de même un bulletin Validé pour rester éligible.
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 16]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 14]);
    Bulletin::factory()->create(['inscription_id' => $inscriptions[1]->id, 'moyenne_generale' => 5]); // Brouillon : ignoré
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[1]->id, 'moyenne_generale' => 9]);

    $inscriptions[0]->update(['decision' => DecisionAnnuelle::Admis]);
    $inscriptions[1]->update(['decision' => DecisionAnnuelle::Redouble]);

    $demande = DemandeGenerationBulletinAnnuel::factory()->create([
        'classe_id' => $classe->id,
        'demande_par_id' => $admin->id,
        'statut' => StatutGenerationBulletin::EnAttente,
    ]);

    (new GenererBulletinsAnnuelsClasseJob($demande))->handle(app(BulletinGenerationService::class));

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Termine);
    expect($demande->nb_bulletins_generes)->toBe(2);
    expect(Storage::exists($demande->chemin_pdf))->toBeTrue();

    expect($inscriptions[0]->fresh()->calculerMoyenneAnnuelle())->toBe(15.0);
    expect($inscriptions[1]->fresh()->calculerMoyenneAnnuelle())->toBe(9.0);
});

test('the bulletin annuel apercu shows the moyenne, rang and décision', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => $inscriptions] = setupClasseAnnuelle();

    Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'systeme' => SystemeScolaire::Primaire]);

    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 18]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[1]->id, 'moyenne_generale' => 10]);
    $inscriptions[0]->update(['decision' => DecisionAnnuelle::Admis, 'observation_annuelle' => 'Excellente année, continuez ainsi.']);

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.annuel.apercu', [
        'classe' => $classe, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    $response->assertSee('Ahouansou');
    $response->assertSee('18.00', false);
    $response->assertSee('1er sur 2', false);
    $response->assertSee('Admis', false);
    $response->assertSee('Excellente année, continuez ainsi.');
});

test('an administrateur can record the décision finale from the bulletins screen', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => $inscriptions] = setupClasseAnnuelle();

    Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'systeme' => SystemeScolaire::Primaire]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 16]);

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id]));

    $response->assertOk();
    $response->assertSee('Décision finale');
    $response->assertSee(route('academique.inscriptions.decision.update', $inscriptions[0]), false);

    $updateResponse = $this->actingAs($admin)->patch(route('academique.inscriptions.decision.update', $inscriptions[0]), [
        'decision' => 'admis',
    ]);

    $updateResponse->assertRedirect();
    $this->assertDatabaseHas('inscriptions', ['id' => $inscriptions[0]->id, 'decision' => 'admis']);
});

test('an agent de scolarité sees the décision but not the edit button on the bulletins screen', function () {
    $agent = User::factory()->agentScolarite()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => $inscriptions] = setupClasseAnnuelle();

    Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'systeme' => SystemeScolaire::Primaire]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 16]);
    $inscriptions[0]->update(['decision' => DecisionAnnuelle::Admis]);

    $response = $this->actingAs($agent)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id]));

    $response->assertOk();
    $response->assertSee('Admis');
    $response->assertDontSee(route('academique.inscriptions.decision.update', $inscriptions[0]), false);
});

test('an individual bulletin annuel PDF can be downloaded on demand', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'anneeAcademique' => $anneeAcademique, 'inscriptions' => $inscriptions] = setupClasseAnnuelle();

    Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id, 'systeme' => SystemeScolaire::Primaire]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscriptions[0]->id, 'moyenne_generale' => 12]);

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.annuel.apercu.telecharger', [
        'classe' => $classe, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(0);
});
