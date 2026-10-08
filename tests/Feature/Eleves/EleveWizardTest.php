<?php

use App\Enums\StatutEleve;
use App\Models\ChampPersonnalise;
use App\Models\Eleve;
use App\Models\Niveau;
use App\Models\ParentTuteur;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\LimitesEnvoi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('enseignants and direction cannot access the fiche élève wizard', function () {
    $enseignant = User::factory()->enseignant()->create();
    $direction = User::factory()->direction()->create();

    $this->actingAs($enseignant)->get(route('eleves.wizard.create'))->assertForbidden();
    $this->actingAs($direction)->get(route('eleves.wizard.create'))->assertForbidden();
});

test('administrators and agents de scolarité can open the wizard to create a new fiche', function () {
    $admin = User::factory()->administrateur()->create();
    $agent = User::factory()->agentScolarite()->create();

    $this->actingAs($admin)->get(route('eleves.wizard.create'))->assertOk();
    $this->actingAs($agent)->get(route('eleves.wizard.create'))->assertOk();
});

test('"Terminer" on a brand-new fiche missing required fields fails validation and creates nothing', function () {
    // "Terminer" (no brouillon flag) is always fully validated — only
    // "Sauvegarder en brouillon" relaxes the rules (see the tests below).
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'nom' => 'Incomplet',
        // prénom, sexe, date de naissance, classe désirée all missing.
    ]);

    $response->assertSessionHasErrors(['prenom', 'sexe', 'date_naissance', 'niveau_souhaite_id']);
    $this->assertDatabaseMissing('eleves', ['nom' => 'Incomplet']);
});

test('"Terminer" succeeds for a Maternelle classe désirée without any transfer documents', function () {
    $agent = User::factory()->agentScolarite()->create();
    $maternelle = Niveau::factory()->maternelle()->create();
    // Not obligatoire, not requis_si_transfert: purely optional, must not block finalisation.
    TypeDocument::factory()->create(['obligatoire' => false, 'requis_si_transfert' => false]);
    // requis_si_transfert only — obligatoire explicitly false, otherwise this
    // factory's random obligatoire default could make the test flaky.
    TypeDocument::factory()->requisSiTransfert()->create(['libelle' => "Bulletin de l'école précédente", 'obligatoire' => false]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $maternelle->id,
        'nom' => 'Adjovi',
        'prenom' => 'Roméo',
        'sexe' => 'M',
        'date_naissance' => '2021-04-12',
    ]);

    $response->assertRedirect(route('eleves.index'));
    $eleve = Eleve::where('nom', 'Adjovi')->firstOrFail();
    expect($eleve->statut)->toBe(StatutEleve::Actif);
});

test('"Terminer" for a non-Maternelle classe désirée requires the transfer documents', function () {
    // The migration that introduced requis_si_transfert (see database/migrations
    // /2026_08_04_000003_...) seeds its own "Bulletin"/"Certificat" rows, which
    // RefreshDatabase re-creates for every test — cleared here so this test's
    // own TypeDocument is the only one requis_si_transfert can find.
    TypeDocument::query()->delete();

    $agent = User::factory()->agentScolarite()->create();
    $cp = Niveau::factory()->create(['libelle' => 'CP']);
    $bulletin = TypeDocument::factory()->requisSiTransfert()->create(['libelle' => "Bulletin de l'école précédente"]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $cp->id,
        'nom' => 'Kokou',
        'prenom' => 'Sena',
        'sexe' => 'M',
        'date_naissance' => '2016-04-12',
    ]);

    $response->assertSessionHasErrors(["documents.{$bulletin->id}"]);
    $this->assertDatabaseMissing('eleves', ['nom' => 'Kokou']);
});

test('"Terminer" for a non-Maternelle classe désirée succeeds once the transfer documents are uploaded', function () {
    Storage::fake('local');
    // See the previous test: clears the migration-seeded "Bulletin"/"Certificat"
    // rows so only this test's own TypeDocument is required.
    TypeDocument::query()->delete();

    $agent = User::factory()->agentScolarite()->create();
    $cp = Niveau::factory()->create(['libelle' => 'CP']);
    $bulletin = TypeDocument::factory()->requisSiTransfert()->create([
        'libelle' => "Bulletin de l'école précédente",
        'formats_acceptes' => ['PDF', 'JPG'],
    ]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $cp->id,
        'nom' => 'Kokou',
        'prenom' => 'Sena',
        'sexe' => 'M',
        'date_naissance' => '2016-04-12',
        'documents' => [
            $bulletin->id => UploadedFile::fake()->create('bulletin.pdf', 100, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect(route('eleves.index'));
    $eleve = Eleve::where('nom', 'Kokou')->firstOrFail();
    expect($eleve->statut)->toBe(StatutEleve::Actif);
    expect($eleve->documents()->where('type_document_id', $bulletin->id)->exists())->toBeTrue();
});

test('uploading a document whose format is not accepted for that type is rejected', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $photo = TypeDocument::factory()->create(['libelle' => 'Photo d\'identité', 'formats_acceptes' => ['JPG', 'PNG']]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Formatinvalide',
        'prenom' => 'Test',
        'sexe' => 'M',
        'date_naissance' => '2021-04-12',
        'documents' => [
            $photo->id => UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf'),
        ],
    ]);

    $response->assertSessionHasErrors(["documents.{$photo->id}"]);
    $this->assertDatabaseMissing('eleves', ['nom' => 'Formatinvalide']);
});

test('finalizing a lingering brouillon (from before "Sauvegarder" was removed) promotes it to Actif', function () {
    Storage::fake('local');
    // See the earlier tests: clears the migration-seeded "Bulletin"/"Certificat"
    // rows so only this test's own TypeDocument is required.
    TypeDocument::query()->delete();

    $agent = User::factory()->agentScolarite()->create();
    $cp = Niveau::factory()->create(['libelle' => 'CP']);
    $bulletin = TypeDocument::factory()->requisSiTransfert()->create(['libelle' => "Bulletin de l'école précédente"]);

    $eleve = Eleve::factory()->create(['statut' => StatutEleve::Brouillon, 'niveau_souhaite_id' => $cp->id]);
    $eleve->documents()->create([
        'type_document_id' => $bulletin->id,
        'televerse_par' => $agent->id,
        'chemin_fichier' => 'documents-eleves/deja-la.pdf',
        'date_ajout' => now()->toDateString(),
    ]);

    $response = $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'niveau_souhaite_id' => $cp->id,
        'nom' => $eleve->nom,
        'prenom' => $eleve->prenom,
        'sexe' => $eleve->sexe,
        'date_naissance' => $eleve->date_naissance->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('eleves.index'));
    expect($eleve->fresh()->statut)->toBe(StatutEleve::Actif);
});

test('editing an already-archived fiche through the wizard does not silently reactivate it', function () {
    // Regression test: since every wizard submission is now always fully
    // validated ("finalisation"), the controller must not treat that as
    // license to force statut back to Actif on an élève that was
    // deliberately archived — only archiver()/désarchiver() may change that.
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $eleve = Eleve::factory()->archive()->create(['niveau_souhaite_id' => $niveau->id]);

    $response = $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => $eleve->nom,
        'prenom' => $eleve->prenom,
        'sexe' => $eleve->sexe,
        'date_naissance' => $eleve->date_naissance->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('eleves.index'));
    expect($eleve->fresh()->statut)->toBe(StatutEleve::Archive);
});

test('champs personnalisés marked obligatoire are always required to save a fiche', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $champ = ChampPersonnalise::factory()->create(['obligatoire' => true, 'libelle' => 'Allergies']);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Sagbo',
        'prenom' => 'Ines',
        'sexe' => 'F',
        'date_naissance' => '2021-04-12',
    ]);

    $response->assertSessionHasErrors(["champs.{$champ->id}"]);
    // Regression test: this used to fall back to Laravel's generic English
    // default ("The champs.5 field is required.") — it must name the champ.
    expect($response->getSession()->get('errors')->first("champs.{$champ->id}"))
        ->toBe('Le champ « Allergies » est obligatoire.');
});

test('editing a fiche without changing its nom/prénom/date de naissance is never blocked as a duplicate, even if another élève happens to share that identity', function () {
    // Regression test: editing an existing fiche isn't creating a new one —
    // an unrelated élève that already happens to share this fiche's exact
    // identity must not keep blocking every future, unrelated edit (e.g.
    // just adding a matricule or a document) once that fiche already exists.
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();

    Eleve::factory()->create(['nom' => 'Adjovi', 'prenom' => 'Roméo', 'date_naissance' => '2021-04-12']);
    $eleve = Eleve::factory()->create(['nom' => 'Adjovi', 'prenom' => 'Roméo', 'date_naissance' => '2021-04-12']);

    $response = $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'niveau_souhaite_id' => $niveau->id,
        'matricule' => '2026-1000',
        'nom' => 'Adjovi',
        'prenom' => 'Roméo',
        'sexe' => $eleve->sexe,
        'date_naissance' => '2021-04-12',
    ]);

    $response->assertSessionDoesntHaveErrors('nom');
    $response->assertRedirect(route('eleves.index'));
});

test('editing a fiche to actually change its identity to match another existing élève is still rejected', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();

    Eleve::factory()->create(['nom' => 'Toko', 'prenom' => 'Ines', 'date_naissance' => '2013-11-02']);
    $eleve = Eleve::factory()->create(['nom' => 'Toko', 'prenom' => 'Autre', 'date_naissance' => '2013-11-02']);

    $response = $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Toko',
        'prenom' => 'Ines',
        'sexe' => $eleve->sexe,
        'date_naissance' => '2013-11-02',
    ]);

    $response->assertSessionHasErrors('nom');
    expect($eleve->fresh()->prenom)->toBe('Autre');
});

test('the wizard edit page prefills existing fiche data', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Marcelline', 'matricule' => 'EM-100']);

    $response = $this->actingAs($agent)->get(route('eleves.wizard.edit', $eleve));

    $response->assertOk();
    $response->assertSee('Houngbo');
    $response->assertSee('Marcelline');
    $response->assertSee('EM-100');
});

test('the tuteur quick-search endpoint finds an existing parent by nom and prénom together', function () {
    $agent = User::factory()->agentScolarite()->create();
    ParentTuteur::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Kokou']);
    ParentTuteur::factory()->create(['nom' => 'Sagbo', 'prenom' => 'Ines']);

    $response = $this->actingAs($agent)->getJson(route('eleves.wizard.tuteurs.recherche', ['q' => 'Kokou Houngbo']));

    $response->assertOk();
    $response->assertJsonCount(1);
    $response->assertJsonFragment(['nom_prenom' => 'Houngbo Kokou']);
});

test('adding a new tuteur through the wizard creates and links a ParentTuteur', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Adjovi',
        'prenom' => 'Roméo',
        'sexe' => 'M',
        'date_naissance' => '2021-04-12',
        'tuteurs' => [
            ['nom_prenom' => 'Adjovi Paul', 'lien_parente' => 'Père', 'telephone' => '22990001122'],
        ],
    ]);

    $response->assertRedirect(route('eleves.index'));
    $eleve = Eleve::where('nom', 'Adjovi')->where('prenom', 'Roméo')->firstOrFail();
    $tuteur = ParentTuteur::where('nom', 'Paul')->where('prenom', 'Adjovi')->first();

    expect($tuteur)->not->toBeNull();
    expect($eleve->parents()->where('parent_tuteurs.id', $tuteur->id)->exists())->toBeTrue();
    expect($eleve->parents()->first()->pivot->lien_parente)->toBe('Père');
});

test('linking an existing tuteur by id through the wizard does not create a duplicate ParentTuteur', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $existant = ParentTuteur::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Kokou']);

    $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Houngbo',
        'prenom' => 'Sena',
        'sexe' => 'F',
        'date_naissance' => '2021-04-12',
        'tuteurs' => [
            ['existing_id' => $existant->id, 'lien_parente' => 'Père'],
        ],
    ]);

    expect(ParentTuteur::where('nom', 'Houngbo')->where('prenom', 'Kokou')->count())->toBe(1);
    $eleve = Eleve::where('nom', 'Houngbo')->where('prenom', 'Sena')->firstOrFail();
    expect($eleve->parents()->where('parent_tuteurs.id', $existant->id)->exists())->toBeTrue();
});

test('a lingering brouillon fiche (from before "Sauvegarder" was removed) is still visible in the élèves list with a "Continuer" action', function () {
    $admin = User::factory()->administrateur()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Enbrouillon', 'statut' => StatutEleve::Brouillon]);

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('Enbrouillon');
    $response->assertSee('Brouillon');
    $response->assertSee(route('eleves.wizard.edit', $eleve), false);
});

test('a brouillon with no nom/prénom yet still renders in the list without error', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create(['nom' => null, 'prenom' => null, 'sexe' => null, 'date_naissance' => null, 'statut' => StatutEleve::Brouillon]);

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('Nouvelle fiche (brouillon)');
});

test('a word document is rejected for a type that only accepts PDF and JPG', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $bulletin = TypeDocument::factory()->create(['libelle' => "Bulletin de l'école précédente", 'formats_acceptes' => ['PDF', 'JPG']]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Formatdocx',
        'prenom' => 'Test',
        'sexe' => 'M',
        'date_naissance' => '2021-04-12',
        'documents' => [
            $bulletin->id => UploadedFile::fake()->create('Choix universités.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ],
    ]);

    $response->assertSessionHasErrors(["documents.{$bulletin->id}"]);
    $this->assertDatabaseMissing('eleves', ['nom' => 'Formatdocx']);
});

test('each document field tells the browser which formats it accepts, so a wrong file is refused as soon as it is dropped', function () {
    $agent = User::factory()->agentScolarite()->create();
    $bulletin = TypeDocument::factory()->create(['formats_acceptes' => ['PDF', 'JPG']]);

    $this->actingAs($agent)->get(route('eleves.wizard.create'))
        ->assertOk()
        ->assertSee('name="documents['.$bulletin->id.']" data-formats="PDF,JPG"', false)
        ->assertSee('data-wizard-dropzone-error', false);
});

test('an upload larger than the server limit sends the agent back with a clear french message instead of a 413 page', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)
        ->from(route('eleves.wizard.create'))
        ->withServerVariables(['CONTENT_LENGTH' => (string) (LimitesEnvoi::octetsMaxParRequete() + 1024)])
        ->post(route('eleves.wizard.store'), ['nom' => 'Tropgros']);

    $response->assertRedirect(route('eleves.wizard.create'));
    $response->assertSessionHasErrors('envoi');
    expect(session('errors')->first('envoi'))->toContain('trop volumineux');
    $this->assertDatabaseMissing('eleves', ['nom' => 'Tropgros']);
});

test('the wizard tells the browser the server upload limits', function () {
    $agent = User::factory()->agentScolarite()->create();

    $this->actingAs($agent)->get(route('eleves.wizard.create'))
        ->assertOk()
        ->assertSee('data-taille-max-envoi="'.LimitesEnvoi::octetsMaxParRequete().'"', false)
        ->assertSee('data-taille-max-fichier="'.LimitesEnvoi::octetsMaxParFichier().'"', false);
});

test('"Sauvegarder en brouillon" saves an incomplete new fiche as a brouillon', function () {
    $agent = User::factory()->agentScolarite()->create();
    TypeDocument::factory()->create(['obligatoire' => true]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'brouillon' => '1',
        'nom' => 'Incomplet',
        'prenom' => 'Marie',
        // classe désirée, sexe, date de naissance and the obligatoire document all missing.
    ]);

    $response->assertRedirect(route('eleves.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('toast', fn (string $toast) => str_contains($toast, 'brouillon'));
    $this->assertDatabaseHas('eleves', ['nom' => 'Incomplet', 'prenom' => 'Marie', 'statut' => StatutEleve::Brouillon->value]);
});

test('a brouillon still needs a nom and a prénom to be found again in the list', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'brouillon' => '1',
        'nom' => 'Sansprenom',
    ]);

    $response->assertSessionHasErrors('prenom');
    $this->assertDatabaseMissing('eleves', ['nom' => 'Sansprenom']);
});

test('a brouillon still rejects a document in the wrong format', function () {
    Storage::fake('local');
    $agent = User::factory()->agentScolarite()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'brouillon' => '1',
        'nom' => 'Brouillon',
        'prenom' => 'Docx',
        'documents' => [$type->id => UploadedFile::fake()->create('bulletin.docx', 10)],
    ]);

    $response->assertSessionHasErrors("documents.{$type->id}");
});

test('saving a brouillon again keeps it a brouillon, and "Terminer" then promotes it to Actif', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Encours', 'prenom' => 'Paul', 'statut' => StatutEleve::Brouillon]);

    $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'brouillon' => '1',
        'nom' => 'Encours',
        'prenom' => 'Paul',
    ])->assertSessionHasNoErrors();

    expect($eleve->fresh()->statut)->toBe(StatutEleve::Brouillon);

    $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Encours',
        'prenom' => 'Paul',
        'sexe' => 'M',
        'date_naissance' => '2021-04-12',
    ])->assertSessionHasNoErrors();

    expect($eleve->fresh()->statut)->toBe(StatutEleve::Actif);
});

test('an Actif fiche can never be turned back into a brouillon', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Actif', 'prenom' => 'Jean', 'statut' => StatutEleve::Actif]);

    $response = $this->actingAs($agent)->patch(route('eleves.wizard.update', $eleve), [
        'brouillon' => '1',
        'nom' => 'Actif',
        'prenom' => 'Jean',
    ]);

    // Fully validated like a normal edit: incomplete → rejected, statut unchanged.
    $response->assertSessionHasErrors(['sexe', 'date_naissance', 'niveau_souhaite_id']);
    expect($eleve->fresh()->statut)->toBe(StatutEleve::Actif);
});

test('the "Sauvegarder en brouillon" button is offered for new fiches but not when editing an Actif one', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create(['statut' => StatutEleve::Actif]);

    $this->actingAs($agent)->get(route('eleves.wizard.create'))
        ->assertOk()
        ->assertSee('Sauvegarder en brouillon');

    $this->actingAs($agent)->get(route('eleves.wizard.edit', $eleve))
        ->assertOk()
        ->assertDontSee('id="wizard-draft-btn"', false);
});

test('the fiche of a brouillon without a date de naissance opens without error', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create(['statut' => StatutEleve::Brouillon, 'date_naissance' => null, 'sexe' => null]);

    $this->actingAs($agent)->getJson(route('eleves.fiche', $eleve))
        ->assertOk()
        ->assertJsonPath('identite.date_naissance', '—');
});

test('editing an Actif fiche can be saved from any step, and says so', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create(['statut' => StatutEleve::Actif]);

    $this->actingAs($agent)->get(route('eleves.wizard.edit', $eleve))
        ->assertOk()
        ->assertSee('data-wizard-mode="edit"', false)
        ->assertSee('Enregistrer les modifications')
        ->assertSee('disponible à chaque étape')
        ->assertSee('id="wizard-back-link"', false);
});

test('a new fiche keeps « Terminer » at the last step', function () {
    $agent = User::factory()->agentScolarite()->create();

    $this->actingAs($agent)->get(route('eleves.wizard.create'))
        ->assertOk()
        ->assertSee('data-wizard-mode="create"', false)
        ->assertSee('>Terminer</button>', false)
        ->assertDontSee('id="wizard-finish-btn">Enregistrer les modifications', false);
});

test('the documents step reminds the maximum file size', function () {
    $agent = User::factory()->agentScolarite()->create();
    TypeDocument::factory()->create(['libelle' => "Photo d'identité", 'formats_acceptes' => ['JPG', 'PNG']]);
    $limite = LimitesEnvoi::enMo(LimitesEnvoi::octetsMaxParFichier());

    $this->actingAs($agent)->get(route('eleves.wizard.create'))
        ->assertOk()
        ->assertSee("Taille maximale : <b>{$limite} par fichier</b>", false)
        ->assertSee("(JPG, PNG — {$limite} max)", false);
});

test('a document over 5 Mo gets a clear french message naming the document', function () {
    Storage::fake('local');
    $agent = User::factory()->agentScolarite()->create();
    $photo = TypeDocument::factory()->create(['libelle' => "Photo d'identité", 'formats_acceptes' => ['JPG']]);

    $response = $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'brouillon' => '1',
        'nom' => 'Photo',
        'prenom' => 'Lourde',
        'documents' => [$photo->id => UploadedFile::fake()->create('photo.jpg', 7 * 1024, 'image/jpeg')],
    ]);

    $response->assertSessionHasErrors("documents.{$photo->id}");
    expect(session('errors')->first("documents.{$photo->id}"))
        ->toContain("« Photo d'identité » est trop volumineux (5 Mo maximum)");
});

test('the same error message is never listed twice in the wizard error summary', function () {
    $agent = User::factory()->agentScolarite()->create();
    // Two types sharing a libellé (as some installs had) both produce the same message.
    TypeDocument::factory()->count(2)->create(['libelle' => 'Pièce en double', 'obligatoire' => true, 'requis_si_transfert' => false]);

    $response = $this->actingAs($agent)
        ->from(route('eleves.wizard.create'))
        ->followingRedirects()
        ->post(route('eleves.wizard.store'), ['nom' => 'Doublon']);

    $html = $response->getContent();
    $resume = substr($html, strpos($html, 'wizard-error-summary'));
    $resume = substr($resume, 0, strpos($resume, '</ul>'));

    expect(substr_count($resume, 'Pièce en double'))->toBe(1);
});

test('the date de début de scolarité is optional and saved when given', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();

    $this->actingAs($agent)->post(route('eleves.wizard.store'), [
        'niveau_souhaite_id' => $niveau->id,
        'nom' => 'Debut',
        'prenom' => 'Ecole',
        'sexe' => 'F',
        'date_naissance' => '2020-03-01',
        'date_debut_scolarite' => '2023-09-18',
    ])->assertSessionHasNoErrors();

    expect(Eleve::query()->where('nom', 'Debut')->firstOrFail()->date_debut_scolarite->format('Y-m-d'))->toBe('2023-09-18');
});

test('the date de début de scolarité cannot be before the date de naissance nor in the future', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->maternelle()->create();
    $base = ['niveau_souhaite_id' => $niveau->id, 'nom' => 'Debut', 'prenom' => 'Ecole', 'sexe' => 'F', 'date_naissance' => '2020-03-01'];

    $this->actingAs($agent)->post(route('eleves.wizard.store'), $base + ['date_debut_scolarite' => '2019-01-01'])
        ->assertSessionHasErrors('date_debut_scolarite');

    $this->actingAs($agent)->post(route('eleves.wizard.store'), $base + ['date_debut_scolarite' => now()->addMonth()->toDateString()])
        ->assertSessionHasErrors('date_debut_scolarite');
});
