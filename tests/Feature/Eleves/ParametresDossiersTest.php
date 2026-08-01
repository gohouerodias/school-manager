<?php

use App\Enums\TypeChampPersonnalise;
use App\Models\ChampPersonnalise;
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
