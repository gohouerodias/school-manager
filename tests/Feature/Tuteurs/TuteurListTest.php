<?php

use App\Models\Eleve;
use App\Models\ParentTuteur;
use App\Models\User;

test('enseignants and direction cannot access the tuteurs list', function () {
    $enseignant = User::factory()->enseignant()->create();
    $direction = User::factory()->direction()->create();

    $this->actingAs($enseignant)->get(route('tuteurs.index'))->assertForbidden();
    $this->actingAs($direction)->get(route('tuteurs.index'))->assertForbidden();
});

test('administrators and agents de scolarité can view the tuteurs list', function () {
    $admin = User::factory()->administrateur()->create();
    $agent = User::factory()->agentScolarite()->create();
    ParentTuteur::factory()->count(2)->create();

    $this->actingAs($admin)->get(route('tuteurs.index'))->assertOk();
    $this->actingAs($agent)->get(route('tuteurs.index'))->assertOk();
});

test('the tuteurs list is filtered server-side by nom, prénom, téléphone or email', function () {
    $admin = User::factory()->administrateur()->create();
    $match = ParentTuteur::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Grégoire', 'telephone' => '+229 97 00 00 00']);
    $other = ParentTuteur::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie', 'telephone' => '+229 96 00 00 00']);

    $byNom = $this->actingAs($admin)->get(route('tuteurs.index', ['search' => 'Ahouansou']));
    $byNom->assertOk();
    $byNom->assertSee('Ahouansou');
    $byNom->assertDontSee('Dossou');

    $byTelephone = $this->actingAs($admin)->get(route('tuteurs.index', ['search' => '96 00 00 00']));
    $byTelephone->assertSee('Dossou');
    $byTelephone->assertDontSee('Ahouansou');

    expect($match->id)->not->toBeNull();
    expect($other->id)->not->toBeNull();
});

test('searching by nom and prénom together still matches, in either order', function () {
    // Regression test: same root cause and fix as the éleves list — see
    // App\Support\MultiWordSearch.
    $admin = User::factory()->administrateur()->create();
    $match = ParentTuteur::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Grégoire']);
    $other = ParentTuteur::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie']);

    $nomPrenom = $this->actingAs($admin)->get(route('tuteurs.index', ['search' => 'Ahouansou Grégoire']));
    $nomPrenom->assertOk();
    $nomPrenom->assertSee('Ahouansou');
    $nomPrenom->assertDontSee('Dossou');

    $prenomNom = $this->actingAs($admin)->get(route('tuteurs.index', ['search' => 'Grégoire Ahouansou']));
    $prenomNom->assertSee('Ahouansou');

    expect($match->id)->not->toBeNull();
    expect($other->id)->not->toBeNull();
});

test('the tuteurs list shows how many élèves each tuteur is linked to', function () {
    $admin = User::factory()->administrateur()->create();
    $tuteur = ParentTuteur::factory()->create();
    $premier = Eleve::factory()->create();
    $second = Eleve::factory()->create();
    $premier->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);
    $second->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);

    $response = $this->actingAs($admin)->get(route('tuteurs.index'));

    $response->assertOk();
    $response->assertSee('data-enfants-url="'.route('tuteurs.enfants', $tuteur).'"', false);
    $response->assertSee('2 enfants');
});

test('a live-search (XMLHttpRequest) request to the tuteurs list returns just the table partial', function () {
    $admin = User::factory()->administrateur()->create();
    ParentTuteur::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Grégoire']);
    ParentTuteur::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie']);

    $response = $this->actingAs($admin)->get(
        route('tuteurs.index', ['search' => 'Ahouansou']),
        ['X-Requested-With' => 'XMLHttpRequest']
    );

    $response->assertOk();
    $response->assertSee('Ahouansou');
    $response->assertDontSee('Dossou');
    // Just the table fragment: no page shell/toolbar around it.
    $response->assertDontSee('<html', false);
    $response->assertDontSee('Rechercher par nom, prénom ou téléphone', false);
});

test('a normal (non-XMLHttpRequest) request to the tuteurs list still returns the full page', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->get(route('tuteurs.index'));

    $response->assertOk();
    $response->assertSee('<html', false);
    $response->assertSee('Rechercher par nom, prénom ou téléphone', false);
});

test('updating a tuteur\'s own info from the list updates the shared record everywhere it is linked', function () {
    $admin = User::factory()->administrateur()->create();
    $tuteur = ParentTuteur::factory()->create(['nom' => 'Ancien', 'prenom' => 'Nom', 'telephone' => '+229 90 00 00 00']);
    $premier = Eleve::factory()->create();
    $second = Eleve::factory()->create();
    $premier->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);
    $second->parents()->attach($tuteur->id, ['lien_parente' => 'Tuteur légal']);

    $response = $this->actingAs($admin)->patch(route('tuteurs.update', $tuteur), [
        'nom_prenom' => 'Grégoire Ahouansou',
        'telephone' => '+229 97 00 00 00',
        'email' => 'gregoire@example.com',
    ]);

    $response->assertRedirect();
    $tuteur->refresh();
    expect($tuteur->nom)->toBe('Ahouansou');
    expect($tuteur->prenom)->toBe('Grégoire');
    expect($tuteur->telephone)->toBe('+229 97 00 00 00');
    // Both élèves' link to this tuteur, and their individual lien_parente,
    // are untouched — only the shared person record changed.
    expect($premier->parents()->where('parent_tuteurs.id', $tuteur->id)->exists())->toBeTrue();
    expect($second->parents()->first()->pivot->lien_parente)->toBe('Tuteur légal');
});

test('updating a tuteur requires nom_prenom and téléphone', function () {
    $admin = User::factory()->administrateur()->create();
    $tuteur = ParentTuteur::factory()->create();

    $response = $this->actingAs($admin)->from(route('tuteurs.index'))->patch(route('tuteurs.update', $tuteur), []);

    $response->assertSessionHasErrors(['nom_prenom', 'telephone']);
});

test('the enfants endpoint returns each linked élève with their lien de parenté', function () {
    $admin = User::factory()->administrateur()->create();
    $tuteur = ParentTuteur::factory()->create();
    $fille = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Chimène']);
    $fils = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Roméo']);
    $fille->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);
    $fils->parents()->attach($tuteur->id, ['lien_parente' => 'Père']);

    $response = $this->actingAs($admin)->getJson(route('tuteurs.enfants', $tuteur));

    $response->assertOk();
    $response->assertJsonCount(2, 'enfants');
    $response->assertJsonFragment(['nom_complet' => 'Ahouansou Chimène', 'lien_parente' => 'Père']);
    $response->assertJsonFragment(['nom_complet' => 'Ahouansou Roméo', 'lien_parente' => 'Père']);
});

test('the enfants endpoint returns an empty list for a tuteur with no élèves linked', function () {
    $admin = User::factory()->administrateur()->create();
    $tuteur = ParentTuteur::factory()->create();

    $response = $this->actingAs($admin)->getJson(route('tuteurs.enfants', $tuteur));

    $response->assertOk();
    $response->assertJsonCount(0, 'enfants');
});

test('the tuteurs list paginates at 50 per page', function () {
    $admin = User::factory()->administrateur()->create();
    ParentTuteur::factory()->count(60)->create();

    $response = $this->actingAs($admin)->get(route('tuteurs.index'));

    $response->assertOk();
    $paginator = $response->viewData('tuteurs');

    expect($paginator->perPage())->toBe(50);
    expect($paginator->total())->toBe(60);
});
