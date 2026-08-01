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
