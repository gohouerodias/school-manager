<?php

use App\Enums\TypeChampPersonnalise;
use App\Models\ChampPersonnalise;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
use App\Models\TypeDocument;
use App\Models\User;

test('only administrators can access paramètres des dossiers', function () {
    $agent = User::factory()->agentScolarite()->create();
    $admin = User::factory()->administrateur()->create();

    $this->actingAs($agent)->get(route('eleves.parametres.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('eleves.parametres.index'))->assertOk();
});

test('administrators can add a type de document with accepted formats', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('eleves.parametres.types-documents.store'), [
        'libelle' => 'Certificat de résidence',
        'formats_acceptes' => ['PDF', 'JPG'],
        'obligatoire' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('types_documents', ['libelle' => 'Certificat de résidence', 'obligatoire' => true]);

    $type = TypeDocument::where('libelle', 'Certificat de résidence')->firstOrFail();
    expect($type->formats_acceptes)->toBe(['PDF', 'JPG']);
});

test('the obligatoire toggle updates a type de document without dropping its formats', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Acte de naissance', 'formats_acceptes' => ['PDF'], 'obligatoire' => false]);

    $response = $this->actingAs($admin)->patch(route('eleves.parametres.types-documents.update', $type), [
        'libelle' => $type->libelle,
        'formats_acceptes' => $type->formats_acceptes,
        'obligatoire' => '1',
    ]);

    $response->assertRedirect();
    expect($type->fresh()->obligatoire)->toBeTrue();
    expect($type->fresh()->formats_acceptes)->toBe(['PDF']);

    $unchecked = $this->actingAs($admin)->patch(route('eleves.parametres.types-documents.update', $type), [
        'libelle' => $type->libelle,
        'formats_acceptes' => $type->formats_acceptes,
    ]);
    $unchecked->assertRedirect();
    expect($type->fresh()->obligatoire)->toBeFalse();
});

test('administrators can delete a type de document', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->create();

    $this->actingAs($admin)->delete(route('eleves.parametres.types-documents.destroy', $type));

    $this->assertDatabaseMissing('types_documents', ['id' => $type->id]);
});

test('a protégé type de document (Photo d\'identité) cannot be renamed, reformatted or toggled', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->protege()->create([
        'libelle' => "Photo d'identité",
        'formats_acceptes' => ['JPG', 'PNG'],
        'obligatoire' => true,
    ]);

    $response = $this->actingAs($admin)->patch(route('eleves.parametres.types-documents.update', $type), [
        'libelle' => 'Nouveau nom',
        'formats_acceptes' => ['PDF'],
        'obligatoire' => '0',
    ]);

    $response->assertRedirect();
    $type->refresh();
    expect($type->libelle)->toBe("Photo d'identité");
    expect($type->formats_acceptes)->toBe(['JPG', 'PNG']);
    expect($type->obligatoire)->toBeTrue();
});

test('a protégé type de document cannot be deleted', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->protege()->create(['libelle' => "Photo d'identité"]);

    $response = $this->actingAs($admin)->delete(route('eleves.parametres.types-documents.destroy', $type));

    $response->assertRedirect();
    $this->assertDatabaseHas('types_documents', ['id' => $type->id]);
});

test('the parametres page hides the edit/delete controls for a protégé type de document', function () {
    $admin = User::factory()->administrateur()->create();
    $protege = TypeDocument::factory()->protege()->create(['libelle' => "Photo d'identité"]);
    $libre = TypeDocument::factory()->create(['libelle' => 'Acte de naissance']);

    $response = $this->actingAs($admin)->get(route('eleves.parametres.index'));

    $response->assertOk();
    $response->assertSee('🔒 Protégé');
    $response->assertSee('data-edit-url="'.route('eleves.parametres.types-documents.update', $libre).'"', false);
    $response->assertDontSee('data-edit-url="'.route('eleves.parametres.types-documents.update', $protege).'"', false);
});

test('administrators can add a texte champ personnalisé', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('eleves.parametres.champs.store'), [
        'libelle' => 'Régime alimentaire',
        'type' => 'texte',
        'obligatoire' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('champs_personnalises', ['libelle' => 'Régime alimentaire', 'type' => 'texte', 'obligatoire' => true]);
});

test('administrators can add a liste déroulante champ personnalisé from newline-separated options', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('eleves.parametres.champs.store'), [
        'libelle' => 'Groupe sanguin',
        'type' => 'liste_deroulante',
        'options_raw' => "A+\nA-\nO+",
    ]);

    $response->assertRedirect();
    $champ = ChampPersonnalise::where('libelle', 'Groupe sanguin')->firstOrFail();
    expect($champ->options)->toBe(['A+', 'A-', 'O+']);
});

test('a liste déroulante champ personnalisé requires at least one option', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->from(route('eleves.parametres.index'))->post(route('eleves.parametres.champs.store'), [
        'libelle' => 'Groupe sanguin',
        'type' => 'liste_deroulante',
        'options_raw' => '',
    ]);

    $response->assertSessionHasErrors('options_raw');
});

test('the "Modifier" panel can rename a type de document and change its accepted formats', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Ancien nom', 'formats_acceptes' => ['PDF'], 'obligatoire' => false]);

    $response = $this->actingAs($admin)->patch(route('eleves.parametres.types-documents.update', $type), [
        'libelle' => 'Nouveau nom',
        'formats_acceptes' => ['JPG', 'PNG'],
        'obligatoire' => '1',
    ]);

    $response->assertRedirect();
    $type->refresh();
    expect($type->libelle)->toBe('Nouveau nom');
    expect($type->formats_acceptes)->toBe(['JPG', 'PNG']);
    expect($type->obligatoire)->toBeTrue();
});

test('editing a type de document without a nom fails validation and leaves it unchanged', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Nom original']);

    $response = $this->actingAs($admin)->from(route('eleves.parametres.index'))->patch(route('eleves.parametres.types-documents.update', $type), [
        'libelle' => '',
        'formats_acceptes' => ['PDF'],
    ]);

    $response->assertSessionHasErrors('libelle');
    expect($type->fresh()->libelle)->toBe('Nom original');
});

test('administrators can toggle and delete a champ personnalisé', function () {
    $admin = User::factory()->administrateur()->create();
    $champ = ChampPersonnalise::factory()->create(['type' => TypeChampPersonnalise::Texte, 'options' => null, 'obligatoire' => false]);

    $toggle = $this->actingAs($admin)->patch(route('eleves.parametres.champs.update', $champ), [
        'libelle' => $champ->libelle,
        'type' => $champ->type->value,
        'obligatoire' => '1',
    ]);
    $toggle->assertRedirect();
    expect($champ->fresh()->obligatoire)->toBeTrue();

    $this->actingAs($admin)->delete(route('eleves.parametres.champs.destroy', $champ));
    $this->assertDatabaseMissing('champs_personnalises', ['id' => $champ->id]);
});

test('the transfer documents (bulletin, certificat) are protégés and cannot be deleted', function () {
    $admin = User::factory()->administrateur()->create();

    foreach (["Bulletin de l'école précédente", 'Certificat de scolarité antérieure'] as $libelle) {
        $type = TypeDocument::query()->where('libelle', $libelle)->firstOrFail();
        expect($type->protege)->toBeTrue();

        $this->actingAs($admin)->delete(route('eleves.parametres.types-documents.destroy', $type));

        $this->assertDatabaseHas('types_documents', ['id' => $type->id]);
    }
});

test('a type de document cannot be created or renamed with a nom already used', function () {
    $admin = User::factory()->administrateur()->create();
    TypeDocument::factory()->create(['libelle' => 'Carnet de vaccination']);
    $autre = TypeDocument::factory()->create(['libelle' => 'Certificat médical scolaire', 'protege' => false]);

    $this->actingAs($admin)->post(route('eleves.parametres.types-documents.store'), [
        'libelle' => 'Carnet de vaccination',
        'formats_acceptes' => ['PDF'],
    ])->assertSessionHasErrors(['libelle' => 'Un type de document porte déjà ce nom.']);

    $this->actingAs($admin)->patch(route('eleves.parametres.types-documents.update', $autre), [
        'libelle' => 'Carnet de vaccination',
        'formats_acceptes' => ['PDF'],
    ])->assertSessionHasErrors('libelle');

    expect(TypeDocument::query()->where('libelle', 'Carnet de vaccination')->count())->toBe(1);
});

test('keeping the same nom when editing a type de document is allowed', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Carnet de vaccination', 'protege' => false]);

    $this->actingAs($admin)->patch(route('eleves.parametres.types-documents.update', $type), [
        'libelle' => 'Carnet de vaccination',
        'formats_acceptes' => ['PDF', 'JPG'],
    ])->assertSessionHasNoErrors();
});

test('a type de document already uploaded for some apprenants cannot be deleted, with a clear message instead of a server error', function () {
    $admin = User::factory()->administrateur()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Carnet de santé', 'protege' => false]);
    $eleves = Eleve::factory()->count(2)->create();
    foreach ($eleves as $eleve) {
        DocumentNumerique::create([
            'eleve_id' => $eleve->id, 'type_document_id' => $type->id, 'televerse_par' => $admin->id,
            'chemin_fichier' => 'documents-eleves/x.pdf', 'date_ajout' => now()->toDateString(),
        ]);
    }

    $this->actingAs($admin)->from(route('eleves.parametres.index'))
        ->delete(route('eleves.parametres.types-documents.destroy', $type))
        ->assertRedirect(route('eleves.parametres.index'))
        ->assertSessionHas('toast', fn (string $toast) => str_contains($toast, 'déjà été déposé pour 2 apprenant(s)'));

    $this->assertDatabaseHas('types_documents', ['id' => $type->id]);
    expect(DocumentNumerique::query()->where('type_document_id', $type->id)->count())->toBe(2);
});

test('the parametres page greys out the delete button of a type already uploaded for apprenants', function () {
    $admin = User::factory()->administrateur()->create();
    $utilise = TypeDocument::factory()->create(['libelle' => 'Carnet utilisé', 'protege' => false]);
    $libre = TypeDocument::factory()->create(['libelle' => 'Carnet libre', 'protege' => false]);
    DocumentNumerique::create([
        'eleve_id' => Eleve::factory()->create()->id, 'type_document_id' => $utilise->id, 'televerse_par' => $admin->id,
        'chemin_fichier' => 'documents-eleves/x.pdf', 'date_ajout' => now()->toDateString(),
    ]);

    $this->actingAs($admin)->get(route('eleves.parametres.index'))
        ->assertOk()
        ->assertSee('déjà déposé pour 1 apprenant(s)', false)
        ->assertSee('Supprimer le type de document « Carnet libre »', false)
        ->assertDontSee('Supprimer le type de document « Carnet utilisé »', false);
});

test('« Dossier élève et documents » is a link in the breadcrumb, followed by a separator', function () {
    $admin = User::factory()->administrateur()->create();

    $this->actingAs($admin)->get(route('eleves.parametres.index'))
        ->assertOk()
        ->assertSee('<a href="'.route('eleves.index').'">Dossier élève et documents</a>', false)
        ->assertSeeInOrder(['Dossier élève et documents</a>', 'breadcrumb-sep', 'Paramètres des dossiers'], false);
});
