<?php

use App\Enums\NiveauQualitatif;
use App\Enums\StatutGenerationBulletin;
use App\Enums\SystemeScolaire;
use App\Jobs\GenererBulletinsClasseJob;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\DemandeGenerationBulletin;
use App\Models\DomaineEvaluation;
use App\Models\Eleve;
use App\Models\EvaluationDomaine;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;
use App\Services\BulletinGenerationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{classe: Classe, examen: Examen, domaine: DomaineEvaluation, classeDomaine: ClasseDomaine, titulaire: User, inscriptions: array<int, Inscription>}
 */
function setupClasseMaternelleAvecBulletins(): array
{
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
    ]);
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeActive->id, 'nom' => 'Maternelle 1-A']);
    $domaine = DomaineEvaluation::factory()->create(['nom' => 'Langage']);
    $classeDomaine = ClasseDomaine::create(['classe_id' => $classe->id, 'domaine_evaluation_id' => $domaine->id]);

    $titulaire = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $titulaire->id, 'classe_id' => $classe->id, 'matiere_id' => null,
        'annee_academique_id' => $anneeActive->id, 'est_professeur_principal' => true,
    ]);

    $eleve1 = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Emmanuel']);
    $eleve2 = Eleve::factory()->create(['nom' => 'Biaou', 'prenom' => 'Grace']);
    $inscription1 = Inscription::factory()->create(['eleve_id' => $eleve1->id, 'classe_id' => $classe->id]);
    $inscription2 = Inscription::factory()->create(['eleve_id' => $eleve2->id, 'classe_id' => $classe->id]);

    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Maternelle,
        'date_examen' => now()->subDays(10)->toDateString(),
        'date_limite_saisie' => now()->addDays(2)->toDateString(),
    ]);

    return ['classe' => $classe, 'examen' => $examen, 'domaine' => $domaine, 'classeDomaine' => $classeDomaine, 'titulaire' => $titulaire, 'inscriptions' => [$inscription1, $inscription2]];
}

/**
 * @param  array<int, Inscription>  $inscriptions
 */
function completerLesDomainesPour(Classe $classe, Examen $examen, ClasseDomaine $classeDomaine, array $inscriptions): void
{
    foreach ($inscriptions as $inscription) {
        EvaluationDomaine::factory()->create([
            'eleve_id' => $inscription->eleve_id,
            'classe_domaine_id' => $classeDomaine->id,
            'examen_id' => $examen->id,
            'valeur' => NiveauQualitatif::Satisfaisant,
        ]);
    }
}

test('the bulletins screen never shows a "Moyenne" column for a classe maternelle', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseMaternelleAvecBulletins();

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id, 'examen_id' => $examen->id]));

    $response->assertOk();
    $response->assertDontSee('Moyenne du mois');
});

test('requesting generation for a maternelle classe is rejected while a domaine évaluation is still missing', function () {
    Queue::fake();

    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseMaternelleAvecBulletins();

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertRedirect();
    expect(DemandeGenerationBulletin::where('classe_id', $classe->id)->exists())->toBeFalse();
    Queue::assertNotPushed(GenererBulletinsClasseJob::class);
});

test('the maternelle paper preview shows the apprenant and the domaine grid, without any matière/moyenne', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'domaine' => $domaine, 'classeDomaine' => $classeDomaine, 'inscriptions' => $inscriptions] = setupClasseMaternelleAvecBulletins();
    EvaluationDomaine::factory()->create([
        'eleve_id' => $inscriptions[0]->eleve_id,
        'classe_domaine_id' => $classeDomaine->id,
        'examen_id' => $examen->id,
        'valeur' => NiveauQualitatif::TresSatisfaisant,
        'observation' => 'Très bonne participation.',
    ]);

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.apercu', [
        'classe' => $classe, 'examen' => $examen, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    $response->assertSee('Ahouansou');
    $response->assertSee($domaine->nom);
    $response->assertSee('Très bonne participation.');
    $response->assertDontSee('Moyenne', false);
});

test('GenererBulletinsClasseJob generates maternelle bulletins without moyenne/rang and produces a downloadable ZIP', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'classeDomaine' => $classeDomaine, 'inscriptions' => $inscriptions] = setupClasseMaternelleAvecBulletins();

    completerLesDomainesPour($classe, $examen, $classeDomaine, $inscriptions);

    $demande = DemandeGenerationBulletin::factory()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
        'statut' => StatutGenerationBulletin::EnAttente,
    ]);

    (new GenererBulletinsClasseJob($demande))->handle(app(BulletinGenerationService::class));

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Termine);
    expect($demande->nb_bulletins_generes)->toBe(2);
    expect(Storage::exists($demande->chemin_pdf))->toBeTrue();

    $bulletin = Bulletin::where('inscription_id', $inscriptions[0]->id)->where('examen_id', $examen->id)->first();
    expect($bulletin)->not->toBeNull();
    expect($bulletin->moyenne_generale)->toBeNull();
    expect($bulletin->rang)->toBeNull();
    expect($bulletin->date_generation)->not->toBeNull();
});

test('an individual maternelle bulletin PDF can be downloaded on demand from the aperçu screen', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseMaternelleAvecBulletins();

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.apercu.telecharger', [
        'classe' => $classe, 'examen' => $examen, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(0);
});
