<?php

use App\Enums\NiveauQualitatif;
use App\Enums\StatutGenerationBulletin;
use App\Enums\SystemeScolaire;
use App\Jobs\GenererBulletinsAnnuelsClasseJob;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\DemandeGenerationBulletinAnnuel;
use App\Models\DomaineEvaluation;
use App\Models\Eleve;
use App\Models\EvaluationDomaine;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;
use App\Services\BulletinGenerationService;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{classe: Classe, anneeAcademique: AnneeAcademique, domaine: DomaineEvaluation, classeDomaine: ClasseDomaine, examens: array<int, Examen>, inscriptions: array<int, Inscription>}
 */
function setupClasseMaternelleAnnuelle(): array
{
    $anneeAcademique = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
        'nombre_evaluations_prevues' => 2,
    ]);
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id, 'nom' => 'Maternelle 2-A']);
    $domaine = DomaineEvaluation::factory()->create(['nom' => 'Langage']);
    $classeDomaine = ClasseDomaine::create(['classe_id' => $classe->id, 'domaine_evaluation_id' => $domaine->id]);

    $eleve = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Emmanuel']);
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $examen1 = Examen::factory()->create([
        'annee_academique_id' => $anneeAcademique->id,
        'systeme' => SystemeScolaire::Maternelle,
        'date_examen' => now()->subMonths(2)->toDateString(),
    ]);
    $examen2 = Examen::factory()->create([
        'annee_academique_id' => $anneeAcademique->id,
        'systeme' => SystemeScolaire::Maternelle,
        'date_examen' => now()->subDays(10)->toDateString(),
    ]);

    return [
        'classe' => $classe,
        'anneeAcademique' => $anneeAcademique,
        'domaine' => $domaine,
        'classeDomaine' => $classeDomaine,
        'examens' => [$examen1, $examen2],
        'inscriptions' => [$inscription],
    ];
}

test('the bulletin annuel maternelle recap tallies TS/S/PS across every examen of the année', function () {
    $admin = User::factory()->administrateur()->create();
    [
        'classe' => $classe,
        'classeDomaine' => $classeDomaine,
        'examens' => $examens,
        'inscriptions' => $inscriptions,
    ] = setupClasseMaternelleAnnuelle();

    EvaluationDomaine::factory()->create([
        'eleve_id' => $inscriptions[0]->eleve_id,
        'classe_domaine_id' => $classeDomaine->id,
        'examen_id' => $examens[0]->id,
        'valeur' => NiveauQualitatif::TresSatisfaisant,
    ]);
    EvaluationDomaine::factory()->create([
        'eleve_id' => $inscriptions[0]->eleve_id,
        'classe_domaine_id' => $classeDomaine->id,
        'examen_id' => $examens[1]->id,
        'valeur' => NiveauQualitatif::TresSatisfaisant,
    ]);

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.annuel.apercu', [
        'classe' => $classe, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    $response->assertSee('Ahouansou');
    $response->assertSee('Langage');

    $service = app(BulletinGenerationService::class);
    $fiche = $service->papierAnnuelPourInscription($classe, $inscriptions[0]);
    $ligneLangage = $fiche['domaines']->firstWhere('nom', 'Langage');

    expect($ligneLangage['ts'])->toBe(2);
    expect($ligneLangage['s'])->toBe(0);
    expect($ligneLangage['ps'])->toBe(0);
});

test('GenererBulletinsAnnuelsClasseJob generates a maternelle annual bulletin for every apprenant regardless of bulletin mensuel status', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'inscriptions' => $inscriptions] = setupClasseMaternelleAnnuelle();

    $demande = DemandeGenerationBulletinAnnuel::factory()->create([
        'classe_id' => $classe->id,
        'demande_par_id' => $admin->id,
        'statut' => StatutGenerationBulletin::EnAttente,
    ]);

    (new GenererBulletinsAnnuelsClasseJob($demande))->handle(app(BulletinGenerationService::class));

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Termine);
    expect($demande->nb_bulletins_generes)->toBe(1);
    expect(Storage::exists($demande->chemin_pdf))->toBeTrue();
});

test('requesting the bulletin annuel maternelle is rejected while the nombre d’évaluations threshold is not reached', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => true, 'nombre_evaluations_prevues' => 5]);
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.annuel.demander'), ['classe_id' => $classe->id]);

    $response->assertRedirect();
    expect(DemandeGenerationBulletinAnnuel::where('classe_id', $classe->id)->exists())->toBeFalse();
});
