<?php

use App\Enums\StatutInscription;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('uploading a document with an inscription_id attaches it to that inscription', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'statut' => StatutInscription::TransfertEntrant]);
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $response = $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'inscription_id' => $inscription->id,
        'fichier' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('documents_numeriques', [
        'eleve_id' => $eleve->id,
        'inscription_id' => $inscription->id,
        'type_document_id' => $type->id,
    ]);
});

test('an inscription_id belonging to another élève is rejected', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $autreEleve = Eleve::factory()->create();
    $inscriptionAutreEleve = Inscription::factory()->create(['eleve_id' => $autreEleve->id]);
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $response = $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'inscription_id' => $inscriptionAutreEleve->id,
        'fichier' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('inscription_id');
    $this->assertDatabaseMissing('documents_numeriques', ['eleve_id' => $eleve->id]);
});

test('a document scoped to one inscription does not collide with the same type scoped to a different inscription', function () {
    Storage::fake('local');

    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $inscriptionA = Inscription::factory()->create(['eleve_id' => $eleve->id]);
    $inscriptionB = Inscription::factory()->create(['eleve_id' => $eleve->id]);
    $type = TypeDocument::factory()->create(['formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'inscription_id' => $inscriptionA->id,
        'fichier' => UploadedFile::fake()->create('certificat-a.pdf', 100, 'application/pdf'),
    ]);
    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'inscription_id' => $inscriptionB->id,
        'fichier' => UploadedFile::fake()->create('certificat-b.pdf', 100, 'application/pdf'),
    ]);

    // 2 documents distincts du même type, un par inscription — pas de
    // remplacement en place, contrairement à un même type sans inscription.
    expect($eleve->documents()->where('type_document_id', $type->id)->count())->toBe(2);
});

test('the fiche élève JSON lists documents attached to each inscription, and the general documents tab excludes them', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $classe = Classe::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id, 'statut' => StatutInscription::TransfertEntrant]);
    $type = TypeDocument::factory()->create(['libelle' => 'Certificat de transfert', 'formats_acceptes' => ['PDF']]);

    $eleve->documents()->create([
        'inscription_id' => $inscription->id,
        'type_document_id' => $type->id,
        'televerse_par' => $agent->id,
        'chemin_fichier' => 'documents-eleves/certificat.pdf',
        'date_ajout' => now()->toDateString(),
    ]);

    $response = $this->actingAs($agent)->getJson(route('eleves.fiche', $eleve));

    $response->assertOk();
    $parcours = collect($response->json('parcours'))->firstWhere('inscription_id', $inscription->id);
    expect($parcours['documents'])->toHaveCount(1);
    expect($parcours['documents'][0]['libelle'])->toBe('Certificat de transfert');
    expect($parcours['document_upload_url'])->toBe(route('eleves.documents.store', $eleve));

    // L'onglet Documents général (un par TypeDocument, "fourni" ou non) ne
    // doit pas compter ce document rattaché à une inscription précise.
    $documentGeneral = collect($response->json('documents'))->firstWhere('libelle', 'Certificat de transfert');
    expect($documentGeneral['fourni'])->toBeFalse();
});
