<?php

use App\Enums\CycleNiveau;
use App\Enums\StatutGenerationBulletin;
use App\Enums\SystemeScolaire;
use App\Jobs\GenererBulletinsClasseJob;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\DemandeGenerationBulletin;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\User;
use App\Services\BulletinGenerationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{classe: Classe, examen: Examen, titulaire: User, inscriptions: array<int, Inscription>}
 */
function setupClasseAvecBulletins(): array
{
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
    ]);
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Primaire]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeActive->id, 'nom' => 'CM1-A']);
    $matiere = Matiere::factory()->create(['nom' => 'Mathématiques']);
    ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 4]);

    $titulaire = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $titulaire->id, 'classe_id' => $classe->id, 'matiere_id' => $matiere->id,
        'annee_academique_id' => $anneeActive->id, 'est_professeur_principal' => true,
    ]);

    $eleve1 = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Emmanuel']);
    $eleve2 = Eleve::factory()->create(['nom' => 'Biaou', 'prenom' => 'Grace']);
    $inscription1 = Inscription::factory()->create(['eleve_id' => $eleve1->id, 'classe_id' => $classe->id]);
    $inscription2 = Inscription::factory()->create(['eleve_id' => $eleve2->id, 'classe_id' => $classe->id]);

    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(10)->toDateString(),
        'date_limite_saisie' => now()->addDays(2)->toDateString(),
    ]);

    return ['classe' => $classe, 'examen' => $examen, 'titulaire' => $titulaire, 'inscriptions' => [$inscription1, $inscription2]];
}

/**
 * Renseigne une Note pour chaque matière du programme de la classe, pour
 * chacune des inscriptions données — c'est cette complétude (et non la
 * signature du titulaire) qui rend une moyenne prête (voir
 * Classe::notesCompletesPour()). setupClasseAvecBulletins() n'a qu'une seule
 * matière (Mathématiques), donc une Note par apprenant suffit ici.
 *
 * @param  array<int, Inscription>  $inscriptions
 */
function completerLesNotesPour(Classe $classe, Examen $examen, array $inscriptions): void
{
    $classeMatiereIds = ClasseMatiere::where('classe_id', $classe->id)->pluck('id');

    foreach ($inscriptions as $inscription) {
        foreach ($classeMatiereIds as $classeMatiereId) {
            Note::factory()->create([
                'eleve_id' => $inscription->eleve_id,
                'classe_matiere_id' => $classeMatiereId,
                'examen_id' => $examen->id,
            ]);
        }
    }
}

test('a non-privileged enseignant cannot access the bulletins screen', function () {
    $enseignant = User::factory()->enseignant()->create();

    $this->actingAs($enseignant)->get(route('eleves.bulletins.index'))->assertForbidden();
});

test('the bulletins screen shows the signature progress for a classe', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id, 'examen_id' => $examen->id]));

    $response->assertOk();
    $response->assertSee('CM1-A');
    $response->assertSee('0 / 2 bulletins signés par le titulaire');
});

test('requesting generation is rejected while at least one moyenne is still pending (notes incomplete)', function () {
    Queue::fake();

    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast', fn (string $toast) => str_contains($toast, 'moyenne'));

    expect(DemandeGenerationBulletin::where('classe_id', $classe->id)->exists())->toBeFalse();
    Queue::assertNotPushed(GenererBulletinsClasseJob::class);
});

test('requesting generation once every moyenne is ready dispatches the job immediately, even without any signature', function () {
    Queue::fake();

    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseAvecBulletins();

    // Aucun Bulletin signé — seules les notes sont complètes. La signature
    // du titulaire n'est plus une condition de génération.
    completerLesNotesPour($classe, $examen, $inscriptions);

    $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
    ])->assertRedirect();

    $demande = DemandeGenerationBulletin::where('classe_id', $classe->id)->where('examen_id', $examen->id)->first();

    expect($demande)->not->toBeNull();
    expect($demande->statut)->toBe(StatutGenerationBulletin::EnAttente);
    expect($demande->genere_at)->toBeNull();

    Queue::assertPushed(GenererBulletinsClasseJob::class, fn (GenererBulletinsClasseJob $job) => $job->demande->is($demande));
});

test('requesting generation while one is already in progress does not dispatch a second job', function () {
    Queue::fake();

    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    DemandeGenerationBulletin::factory()->enCours()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast', fn (string $toast) => str_contains($toast, 'déjà en cours'));
    Queue::assertNotPushed(GenererBulletinsClasseJob::class);
});

test('an EnAttente demande stuck for more than 2 minutes without a worker no longer blocks a new generation', function () {
    Queue::fake();

    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseAvecBulletins();
    completerLesNotesPour($classe, $examen, $inscriptions);

    $demande = DemandeGenerationBulletin::factory()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
        'statut' => StatutGenerationBulletin::EnAttente,
        'demande_at' => now()->subMinutes(5),
    ]);

    expect($demande->estCoinceeSansWorker())->toBeTrue();
    expect($demande->bloqueUneNouvelleGeneration())->toBeFalse();

    // L'écran ne doit plus afficher le bouton comme désactivé, et doit
    // signaler le blocage plutôt que de rester silencieusement sur le spinner.
    $indexResponse = $this->actingAs($admin)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id, 'examen_id' => $examen->id]));
    $indexResponse->assertSee('semble bloquée depuis plus de 2 minutes', false);
    expect($indexResponse->getContent())->not->toMatch('/id="generate-bulletins-btn"[^>]*disabled/');

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertRedirect();
    Queue::assertPushed(GenererBulletinsClasseJob::class);
});

test('a genuinely fresh EnAttente/EnCours demande still blocks a new generation', function () {
    Queue::fake();

    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    $demande = DemandeGenerationBulletin::factory()->enCours()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
        'demande_at' => now(),
    ]);

    expect($demande->estCoinceeSansWorker())->toBeFalse();
    expect($demande->bloqueUneNouvelleGeneration())->toBeTrue();

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast', fn (string $toast) => str_contains($toast, 'déjà en cours'));
    Queue::assertNotPushed(GenererBulletinsClasseJob::class);
});

test('the paper preview shows the apprenant and the matière', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseAvecBulletins();

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.apercu', [
        'classe' => $classe, 'examen' => $examen, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    $response->assertSee('Ahouansou');
    $response->assertSee('Mathématiques');
});

test('the statut endpoint reports live progress while a generation is en cours', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    $demande = DemandeGenerationBulletin::factory()->enCours(traites: 1, total: 2)->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->getJson(route('eleves.bulletins.statut', $demande));

    $response->assertOk();
    $response->assertJson([
        'statut' => 'en_cours',
        'traites' => 1,
        'total' => 2,
        'pourcentage' => 50,
        'genere' => false,
        'echec' => false,
        'telechargerUrl' => null,
    ]);
});

test('GenererBulletinsClasseJob generates the bulletins, tracks progress, and produces a downloadable ZIP of individual PDFs', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseAvecBulletins();

    // Aucun Bulletin n'existe encore et personne ne l'a signé — seules les
    // notes sont complètes ; genererPourClasse() doit tout de même créer le
    // Bulletin de chaque apprenant à la volée (voir updateOrCreate()).
    completerLesNotesPour($classe, $examen, $inscriptions);

    $demande = DemandeGenerationBulletin::factory()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
        'statut' => StatutGenerationBulletin::EnAttente,
    ]);

    (new GenererBulletinsClasseJob($demande))->handle(app(BulletinGenerationService::class));

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Termine);
    expect($demande->total)->toBe(2);
    expect($demande->traites)->toBe(2);
    expect($demande->genere_at)->not->toBeNull();
    expect($demande->nb_bulletins_generes)->toBe(2);
    expect($demande->chemin_pdf)->not->toBeNull();
    expect($demande->chemin_pdf)->toEndWith('.zip');
    expect(Storage::exists($demande->chemin_pdf))->toBeTrue();

    // L'archive contient bien un PDF distinct par apprenant, pas un unique
    // PDF groupé — App\Support\ZipWriter écrit le ZIP lui-même (sans
    // dépendre de l'extension `zip`), donc on ne s'appuie sur ZipArchive
    // ici que si elle est disponible ; sinon on vérifie les signatures ZIP
    // brutes pour rester indépendant de la configuration PHP de la machine.
    $bytes = Storage::get($demande->chemin_pdf);
    expect(substr($bytes, 0, 4))->toBe("PK\x03\x04");
    expect(substr_count($bytes, "PK\x01\x02"))->toBe(2);

    if (extension_loaded('zip')) {
        $zip = new ZipArchive;
        expect($zip->open(Storage::path($demande->chemin_pdf)))->toBeTrue();
        expect($zip->count())->toBe(2);
        for ($i = 0; $i < $zip->count(); $i++) {
            expect($zip->getNameIndex($i))->toEndWith('.pdf');
        }
        $zip->close();
    }

    $bulletin = Bulletin::where('inscription_id', $inscriptions[0]->id)->where('examen_id', $examen->id)->first();
    expect($bulletin->moyenne_generale)->not->toBeNull();
    expect($bulletin->rang)->not->toBeNull();
    expect($bulletin->date_generation)->not->toBeNull();

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.telecharger', $demande));
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.zip');
});

test('a classe already generated can be regenerated, replacing the previous archive', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseAvecBulletins();

    completerLesNotesPour($classe, $examen, $inscriptions);

    // Première génération.
    $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id, 'examen_id' => $examen->id,
    ]);

    $demande = DemandeGenerationBulletin::where('classe_id', $classe->id)->where('examen_id', $examen->id)->firstOrFail();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Termine);
    $ancienChemin = $demande->chemin_pdf;
    expect(Storage::exists($ancienChemin))->toBeTrue();

    // L'écran doit maintenant proposer de RÉgénérer, pas juste générer.
    $response = $this->actingAs($admin)->get(route('eleves.bulletins.index', ['classe_id' => $classe->id, 'examen_id' => $examen->id]));
    $response->assertSee('Régénérer les bulletins de la classe');
    $response->assertSee('data-confirm-submit', false);

    // Deuxième génération (régénération) : autorisée, remplace l'archive.
    $response = $this->actingAs($admin)->post(route('eleves.bulletins.demander'), [
        'classe_id' => $classe->id, 'examen_id' => $examen->id,
    ]);
    $response->assertRedirect();
    $response->assertSessionHas('toast', fn (string $toast) => str_contains($toast, 'Régénération'));

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Termine);
    expect(Storage::exists($demande->chemin_pdf))->toBeTrue();

    // L'ancienne archive a bien été supprimée, pas de fichier orphelin.
    expect(Storage::exists($ancienChemin))->toBeFalse();

    // Toujours une seule DemandeGenerationBulletin pour ce couple classe/examen.
    expect(DemandeGenerationBulletin::where('classe_id', $classe->id)->where('examen_id', $examen->id)->count())->toBe(1);
});

test('an individual bulletin PDF can be downloaded on demand from the aperçu screen', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen, 'inscriptions' => $inscriptions] = setupClasseAvecBulletins();

    $response = $this->actingAs($admin)->get(route('eleves.bulletins.apercu.telecharger', [
        'classe' => $classe, 'examen' => $examen, 'inscription' => $inscriptions[0],
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect(strlen($response->getContent()))->toBeGreaterThan(0);
});

test('downloading an individual bulletin PDF for an inscription outside the classe is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();
    ['classe' => $autreClasse] = setupClasseAvecBulletins();
    $autreInscription = Inscription::where('classe_id', $autreClasse->id)->first();

    $this->actingAs($admin)->get(route('eleves.bulletins.apercu.telecharger', [
        'classe' => $classe, 'examen' => $examen, 'inscription' => $autreInscription,
    ]))->assertNotFound();
});

test('GenererBulletinsClasseJob records the failure without crashing when generation throws', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    $demande = DemandeGenerationBulletin::factory()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
        'statut' => StatutGenerationBulletin::EnAttente,
    ]);

    $service = Mockery::mock(BulletinGenerationService::class);
    $service->shouldReceive('genererPourClasse')->once()->andThrow(new RuntimeException('Disque plein.'));

    (new GenererBulletinsClasseJob($demande))->handle($service);

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Echec);
    expect($demande->erreur)->toContain('Disque plein.');
});

test('the failed() safety-net marks the demande as échec if the job is killed before completing', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'examen' => $examen] = setupClasseAvecBulletins();

    $demande = DemandeGenerationBulletin::factory()->enCours()->create([
        'classe_id' => $classe->id,
        'examen_id' => $examen->id,
        'demande_par_id' => $admin->id,
    ]);

    (new GenererBulletinsClasseJob($demande))->failed(new RuntimeException('Worker tué.'));

    $demande->refresh();
    expect($demande->statut)->toBe(StatutGenerationBulletin::Echec);
    expect($demande->erreur)->toBe('Worker tué.');
});
