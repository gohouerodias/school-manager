<?php

use App\Models\Eleve;
use App\Models\ParentTuteur;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('adding a tuteur splits "nom et prénom" and attaches it to the fiche élève', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($agent)->post(route('eleves.tuteurs.store', $eleve), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '+229 97 00 00 00',
        'email' => 'gregoire@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('parent_tuteurs', [
        'nom' => 'Ahouansou',
        'prenom' => 'Grégoire',
        'telephone' => '+229 97 00 00 00',
        'email' => 'gregoire@example.com',
    ]);

    $tuteur = ParentTuteur::where('nom', 'Ahouansou')->firstOrFail();
    $this->assertDatabaseHas('eleve_parent', [
        'eleve_id' => $eleve->id,
        'parent_tuteur_id' => $tuteur->id,
        'lien_parente' => 'Père',
    ]);
});

test('adding a tuteur matching an existing parent_tuteur (nom+prénom+téléphone) reuses it instead of duplicating', function () {
    $agent = User::factory()->agentScolarite()->create();
    $premierEnfant = Eleve::factory()->create();
    $deuxiemeEnfant = Eleve::factory()->create();

    $existant = ParentTuteur::factory()->create([
        'nom' => 'Ahouansou',
        'prenom' => 'Grégoire',
        'telephone' => '+229 97 00 00 00',
    ]);
    $premierEnfant->parents()->attach($existant->id, ['lien_parente' => 'Père']);

    $response = $this->actingAs($agent)->post(route('eleves.tuteurs.store', $deuxiemeEnfant), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '+229 97 00 00 00',
    ]);

    $response->assertRedirect();
    // Still only one ParentTuteur row for this person, not a duplicate.
    expect(ParentTuteur::where('nom', 'Ahouansou')->where('prenom', 'Grégoire')->count())->toBe(1);
    $this->assertDatabaseHas('eleve_parent', [
        'eleve_id' => $deuxiemeEnfant->id,
        'parent_tuteur_id' => $existant->id,
        'lien_parente' => 'Père',
    ]);
    // The first child keeps their own link to the same tuteur.
    $this->assertDatabaseHas('eleve_parent', [
        'eleve_id' => $premierEnfant->id,
        'parent_tuteur_id' => $existant->id,
    ]);
});

test('re-adding a tuteur already linked to this élève updates their role instead of failing', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $tuteur = ParentTuteur::factory()->create(['nom' => 'Zannou', 'prenom' => 'Estelle', 'telephone' => '+229 96 11 22 33']);
    $eleve->parents()->attach($tuteur->id, ['lien_parente' => 'Mère']);

    $response = $this->actingAs($agent)->post(route('eleves.tuteurs.store', $eleve), [
        'nom_prenom' => 'Estelle Zannou',
        'lien_parente' => 'Tuteur légal',
        'telephone' => '+229 96 11 22 33',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('eleve_parent', [
        'eleve_id' => $eleve->id,
        'parent_tuteur_id' => $tuteur->id,
        'lien_parente' => 'Tuteur légal',
    ]);
    expect($eleve->parents()->count())->toBe(1);
});

test('updating a tuteur edits their own record and this élève\'s lien de parenté', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $tuteur = ParentTuteur::factory()->create(['nom' => 'Zannou', 'prenom' => 'Estelle', 'telephone' => '+229 96 11 22 33']);
    $eleve->parents()->attach($tuteur->id, ['lien_parente' => 'Mère']);

    $response = $this->actingAs($agent)->patch(route('eleves.tuteurs.update', [$eleve, $tuteur]), [
        'nom_prenom' => 'Estelle Zannou-Ahouansou',
        'lien_parente' => 'Tuteur légal',
        'telephone' => '+229 96 99 88 77',
        'email' => 'estelle@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('parent_tuteurs', [
        'id' => $tuteur->id,
        'nom' => 'Zannou-Ahouansou',
        'prenom' => 'Estelle',
        'telephone' => '+229 96 99 88 77',
        'email' => 'estelle@example.com',
    ]);
    $this->assertDatabaseHas('eleve_parent', [
        'eleve_id' => $eleve->id,
        'parent_tuteur_id' => $tuteur->id,
        'lien_parente' => 'Tuteur légal',
    ]);
});

test('updating a tuteur also updates their shared record for other élèves (fratrie)', function () {
    $agent = User::factory()->agentScolarite()->create();
    $premierEnfant = Eleve::factory()->create();
    $deuxiemeEnfant = Eleve::factory()->create();
    $tuteur = ParentTuteur::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Grégoire', 'telephone' => '+229 97 00 00 00']);
    $premierEnfant->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);
    $deuxiemeEnfant->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);

    $this->actingAs($agent)->patch(route('eleves.tuteurs.update', [$premierEnfant, $tuteur]), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '+229 97 55 55 55',
    ]);

    $this->assertDatabaseHas('parent_tuteurs', ['id' => $tuteur->id, 'telephone' => '+229 97 55 55 55']);
    // Both children still point at the same (now-updated) tuteur record.
    $this->assertDatabaseHas('eleve_parent', ['eleve_id' => $premierEnfant->id, 'parent_tuteur_id' => $tuteur->id]);
    $this->assertDatabaseHas('eleve_parent', ['eleve_id' => $deuxiemeEnfant->id, 'parent_tuteur_id' => $tuteur->id]);
});

test('updating a tuteur to match another existing tuteur links that one instead of duplicating', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $tuteurACorriger = ParentTuteur::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Gregoir', 'telephone' => '+229 97 00 00 01']);
    $eleve->parents()->attach($tuteurACorriger->id, ['lien_parente' => 'Père']);
    $tuteurExistant = ParentTuteur::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Grégoire', 'telephone' => '+229 97 00 00 00']);

    $response = $this->actingAs($agent)->patch(route('eleves.tuteurs.update', [$eleve, $tuteurACorriger]), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '+229 97 00 00 00',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('eleve_parent', [
        'eleve_id' => $eleve->id,
        'parent_tuteur_id' => $tuteurExistant->id,
        'lien_parente' => 'Père',
    ]);
    $this->assertDatabaseMissing('eleve_parent', [
        'eleve_id' => $eleve->id,
        'parent_tuteur_id' => $tuteurACorriger->id,
    ]);
});

test('updating a tuteur not linked to this élève is rejected', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $tuteur = ParentTuteur::factory()->create();

    $response = $this->actingAs($agent)->patch(route('eleves.tuteurs.update', [$eleve, $tuteur]), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '+229 97 00 00 00',
    ]);

    $response->assertNotFound();
});

test('updating a tuteur without a telephone fails validation', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $tuteur = ParentTuteur::factory()->create();
    $eleve->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);

    $response = $this->actingAs($agent)->from(route('eleves.index'))->patch(route('eleves.tuteurs.update', [$eleve, $tuteur]), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '',
    ]);

    $response->assertSessionHasErrors('telephone');
});

test('adding a tuteur without a telephone fails validation', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($agent)->from(route('eleves.index'))->post(route('eleves.tuteurs.store', $eleve), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'lien_parente' => 'Père',
        'telephone' => '',
    ]);

    $response->assertSessionHasErrors('telephone');
});

test('uploading a document accepted by the type succeeds and stores the file', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['JPG', 'PDF']]);

    $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => $file,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('documents_numeriques', [
        'eleve_id' => $eleve->id,
        'type_document_id' => $type->id,
        'televerse_par' => $agent->id,
    ]);

    $document = $eleve->documents()->first();
    Storage::disk('local')->assertExists($document->chemin_fichier);
});

test('re-uploading a document of a type already on file replaces it instead of duplicating (e.g. changing the photo d\'identité)', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->protege()->create(['libelle' => "Photo d'identité", 'formats_acceptes' => ['JPG', 'PNG']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('ancienne-photo.jpg', 100, 'image/jpeg'),
    ]);
    $original = $eleve->documents()->where('type_document_id', $type->id)->firstOrFail();
    $ancienChemin = $original->chemin_fichier;
    Storage::disk('local')->assertExists($ancienChemin);

    $response = $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('nouvelle-photo.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertRedirect();
    // Still a single row for this type — the old one was updated in place,
    // not duplicated.
    expect($eleve->documents()->where('type_document_id', $type->id)->count())->toBe(1);

    $updated = $eleve->documents()->where('type_document_id', $type->id)->firstOrFail();
    expect($updated->id)->toBe($original->id);
    expect($updated->chemin_fichier)->not->toBe($ancienChemin);
    Storage::disk('local')->assertMissing($ancienChemin);
    Storage::disk('local')->assertExists($updated->chemin_fichier);
});

test('uploading a document with a format not accepted by the type fails validation', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($agent)->from(route('eleves.index'))->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => $file,
    ]);

    $response->assertSessionHasErrors('fichier');
    $this->assertDatabaseMissing('documents_numeriques', ['eleve_id' => $eleve->id]);
});

test('uploading a document over 5 Mo fails validation', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $file = UploadedFile::fake()->create('gros-fichier.pdf', 6000, 'application/pdf');

    $response = $this->actingAs($agent)->from(route('eleves.index'))->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => $file,
    ]);

    $response->assertSessionHasErrors('fichier');
});

test('deleting a tuteur detaches it from the fiche élève without deleting the record', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $tuteur = ParentTuteur::factory()->create();
    $eleve->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);

    $response = $this->actingAs($agent)->delete(route('eleves.tuteurs.destroy', [$eleve, $tuteur]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('eleve_parent', [
        'eleve_id' => $eleve->id,
        'parent_tuteur_id' => $tuteur->id,
    ]);
    $this->assertDatabaseHas('parent_tuteurs', ['id' => $tuteur->id]);
});

test('deleting a document removes both the database row and the stored file', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carte.pdf', 100, 'application/pdf'),
    ]);

    $document = $eleve->documents()->first();
    $chemin = $document->chemin_fichier;
    Storage::disk('local')->assertExists($chemin);

    $response = $this->actingAs($agent)->delete(route('eleves.documents.destroy', [$eleve, $document]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('documents_numeriques', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($chemin);
});

test('deleting a document belonging to another élève is rejected', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $autreEleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carte.pdf', 100, 'application/pdf'),
    ]);
    $document = $eleve->documents()->first();

    $response = $this->actingAs($agent)->delete(route('eleves.documents.destroy', [$autreEleve, $document]));

    $response->assertNotFound();
    $this->assertDatabaseHas('documents_numeriques', ['id' => $document->id]);
});

test('viewing a stored document streams it inline', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Certificat médical', 'formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carte.pdf', 100, 'application/pdf'),
    ]);
    $document = $eleve->documents()->first();

    $response = $this->actingAs($agent)->get(route('eleves.documents.show', [$eleve, $document]));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

test('downloading a stored document forces an attachment response', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Certificat médical', 'formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carte.pdf', 100, 'application/pdf'),
    ]);
    $document = $eleve->documents()->first();

    $response = $this->actingAs($agent)->get(route('eleves.documents.download', [$eleve, $document]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->headers->get('content-disposition'))->toContain('certificat-medical');
});

test('viewing a document belonging to another élève is rejected', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $autreEleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carte.pdf', 100, 'application/pdf'),
    ]);
    $document = $eleve->documents()->first();

    $response = $this->actingAs($agent)->get(route('eleves.documents.show', [$autreEleve, $document]));

    $response->assertNotFound();
});

test('the fiche élève exposes the photo d\'identité document as a photo_url', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['libelle' => "Photo d'identité", 'formats_acceptes' => ['JPG', 'PNG']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
    ]);
    $document = $eleve->documents()->first();

    $response = $this->actingAs($agent)->getJson(route('eleves.fiche', $eleve));

    $response->assertOk();
    $response->assertJsonPath('identite.photo_url', route('eleves.documents.show', [$eleve, $document]));
});

test('the fiche élève has no photo_url when no photo d\'identité document is on file', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($agent)->getJson(route('eleves.fiche', $eleve));

    $response->assertOk();
    $response->assertJsonPath('identite.photo_url', null);
});

test('the apprenants list shows the photo d\'identité as the avatar when one is on file', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['libelle' => "Photo d'identité", 'formats_acceptes' => ['JPG', 'PNG']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
    ]);
    $document = $eleve->documents()->first();

    $response = $this->actingAs($agent)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee(route('eleves.documents.show', [$eleve, $document]), false);
});

test('viewing a document whose stored file is missing returns 404', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carte.pdf', 100, 'application/pdf'),
    ]);
    $document = $eleve->documents()->first();
    Storage::disk('local')->delete($document->chemin_fichier);

    $response = $this->actingAs($agent)->get(route('eleves.documents.show', [$eleve, $document]));

    $response->assertNotFound();
});
