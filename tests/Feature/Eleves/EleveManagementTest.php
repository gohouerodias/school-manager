<?php

use App\Enums\StatutEleve;
use App\Models\AnneeAcademique;
use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;

test('enseignants and direction cannot access the eleves list', function () {
    $enseignant = User::factory()->enseignant()->create();
    $direction = User::factory()->direction()->create();

    $this->actingAs($enseignant)->get(route('eleves.index'))->assertForbidden();
    $this->actingAs($direction)->get(route('eleves.index'))->assertForbidden();
});

test('administrators and agents de scolarité can view the eleves list', function () {
    $admin = User::factory()->administrateur()->create();
    $agent = User::factory()->agentScolarite()->create();
    Eleve::factory()->count(2)->create();

    $this->actingAs($admin)->get(route('eleves.index'))->assertOk();
    $this->actingAs($agent)->get(route('eleves.index'))->assertOk();
});

test('the classe filter dropdown options use the real classe ids, not renumbered indexes', function () {
    // Regression test: the options used to be built with Collection::merge()
    // (array_merge() under the hood), which renumbers integer-like keys —
    // so a classe with a real DB id of 1 was rendered as <option value="0">
    // and selecting it filtered on the wrong id, showing nothing.
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $niveauCI = Niveau::factory()->create(['libelle' => 'CI']);
    $niveauCP = Niveau::factory()->create(['libelle' => 'CP']);
    $classeCI = Classe::factory()->create(['niveau_id' => $niveauCI->id, 'annee_academique_id' => $anneeActive->id, 'nom' => 'A']);
    $classeCP = Classe::factory()->create(['niveau_id' => $niveauCP->id, 'annee_academique_id' => $anneeActive->id, 'nom' => 'A']);

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('<option value="'.$classeCI->id.'" >CI — A</option>', false);
    $response->assertSee('<option value="'.$classeCP->id.'" >CP — A</option>', false);
});

test('filtering the eleves list by classe returns only élèves inscrits in that classe', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);
    $classeA = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);
    $classeB = Classe::factory()->create(['annee_academique_id' => $anneeActive->id]);

    $eleveA = Eleve::factory()->create(['nom' => 'Dansclassea']);
    Inscription::factory()->create(['eleve_id' => $eleveA->id, 'classe_id' => $classeA->id]);
    $eleveB = Eleve::factory()->create(['nom' => 'Dansclasseb']);
    Inscription::factory()->create(['eleve_id' => $eleveB->id, 'classe_id' => $classeB->id]);

    $response = $this->actingAs($admin)->get(route('eleves.index', ['classe' => $classeA->id]));

    $response->assertOk();
    $response->assertSee('Dansclassea');
    $response->assertDontSee('Dansclasseb');
});

test('creating a fiche élève generates a sequential matricule and stores the fixed fields', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->post(route('eleves.store'), [
        'nom' => 'Adjovi',
        'prenom' => 'Roméo',
        'sexe' => 'M',
        'date_naissance' => '2015-04-12',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('eleves', ['nom' => 'Adjovi', 'prenom' => 'Roméo', 'sexe' => 'M']);

    $eleve = Eleve::where('nom', 'Adjovi')->firstOrFail();
    expect($eleve->matricule)->toStartWith((string) now()->year.'-');
});

test('creating a fiche élève requires nom, prénom, sexe and date de naissance', function () {
    $agent = User::factory()->agentScolarite()->create();

    $response = $this->actingAs($agent)->from(route('eleves.index'))->post(route('eleves.store'), []);

    $response->assertSessionHasErrors(['nom', 'prenom', 'sexe', 'date_naissance']);
    $this->assertDatabaseCount('eleves', 0);
});

test('creating a fiche élève requires obligatoire champs personnalisés but not optional ones', function () {
    $agent = User::factory()->agentScolarite()->create();
    $obligatoire = ChampPersonnalise::factory()->create(['libelle' => 'Nationalité', 'obligatoire' => true]);
    $optionnel = ChampPersonnalise::factory()->create(['libelle' => 'Allergies', 'obligatoire' => false]);

    $missing = $this->actingAs($agent)->from(route('eleves.index'))->post(route('eleves.store'), [
        'nom' => 'Sossou', 'prenom' => 'Bénie', 'sexe' => 'F', 'date_naissance' => '2016-01-01',
    ]);
    $missing->assertSessionHasErrors("champs.{$obligatoire->id}");

    $ok = $this->actingAs($agent)->post(route('eleves.store'), [
        'nom' => 'Sossou', 'prenom' => 'Bénie', 'sexe' => 'F', 'date_naissance' => '2016-01-01',
        'champs' => [$obligatoire->id => 'Béninoise'],
    ]);
    $ok->assertRedirect();

    $eleve = Eleve::where('nom', 'Sossou')->firstOrFail();
    $this->assertDatabaseHas('valeurs_champs_personnalises', [
        'eleve_id' => $eleve->id,
        'champ_personnalise_id' => $obligatoire->id,
        'valeur' => 'Béninoise',
    ]);
    $this->assertDatabaseMissing('valeurs_champs_personnalises', [
        'eleve_id' => $eleve->id,
        'champ_personnalise_id' => $optionnel->id,
    ]);
});

test('creating a fiche élève with a classe désirée (niveau souhaité) stores it without creating an inscription', function () {
    $agent = User::factory()->agentScolarite()->create();
    $niveau = Niveau::factory()->create(['libelle' => 'CM2']);

    $response = $this->actingAs($agent)->post(route('eleves.store'), [
        'nom' => 'Agbo', 'prenom' => 'Chimène', 'sexe' => 'F', 'date_naissance' => '2015-09-01',
        'niveau_souhaite_id' => $niveau->id,
    ]);

    $response->assertRedirect();
    $eleve = Eleve::where('nom', 'Agbo')->firstOrFail();
    expect($eleve->niveau_souhaite_id)->toBe($niveau->id);
    expect($eleve->inscriptions()->count())->toBe(0);
});

test('creating a duplicate fiche élève (same nom, prénom, date de naissance) is rejected', function () {
    $agent = User::factory()->agentScolarite()->create();
    $existant = Eleve::factory()->create([
        'nom' => 'Dossou', 'prenom' => 'Marcel', 'date_naissance' => '2016-03-15',
    ]);

    $response = $this->actingAs($agent)->from(route('eleves.index'))->post(route('eleves.store'), [
        'nom' => 'Dossou', 'prenom' => 'Marcel', 'sexe' => 'M', 'date_naissance' => '2016-03-15',
    ]);

    $response->assertSessionHasErrors('nom');
    expect(Eleve::where('nom', 'Dossou')->where('prenom', 'Marcel')->count())->toBe(1);
    expect($existant->fresh())->not->toBeNull();
});

test('updating a fiche élève keeping its own nom, prénom and date de naissance is not flagged as a duplicate', function () {
    $admin = User::factory()->administrateur()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Koffi', 'prenom' => 'Rachelle', 'date_naissance' => '2014-05-10']);

    $response = $this->actingAs($admin)->patch(route('eleves.update', $eleve), [
        'nom' => 'Koffi', 'prenom' => 'Rachelle', 'sexe' => $eleve->sexe, 'date_naissance' => '2014-05-10',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('nom');
});

test('updating a fiche élève to match another existing élève is rejected', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create(['nom' => 'Toko', 'prenom' => 'Ines', 'date_naissance' => '2013-11-02']);
    $eleve = Eleve::factory()->create(['nom' => 'Toko', 'prenom' => 'Autre', 'date_naissance' => '2013-11-02']);

    $response = $this->actingAs($admin)->from(route('eleves.index'))->patch(route('eleves.update', $eleve), [
        'nom' => 'Toko', 'prenom' => 'Ines', 'sexe' => $eleve->sexe, 'date_naissance' => '2013-11-02',
    ]);

    $response->assertSessionHasErrors('nom');
    expect($eleve->fresh()->prenom)->toBe('Autre');
});

test('creation no longer accepts a direct classe assignment: the élève stays sans classe attribuée regardless', function () {
    // "Classe désirée" (niveau_souhaite_id) replaced the old direct classe_id
    // field: creating a fiche élève never creates an Inscription by itself
    // anymore — a real classe is only assigned later, by the censeur, during
    // the répartition for the année académique.
    $agent = User::factory()->agentScolarite()->create();
    $classe = Classe::factory()->create();

    $response = $this->actingAs($agent)->post(route('eleves.store'), [
        'nom' => 'Houngbo', 'prenom' => 'Kokou', 'sexe' => 'M', 'date_naissance' => '2014-06-20',
        'classe_id' => $classe->id,
    ]);

    $response->assertRedirect();
    $eleve = Eleve::where('nom', 'Houngbo')->firstOrFail();
    expect($eleve->inscriptions()->count())->toBe(0);
});

test('updating a fiche élève updates fixed fields and champ values', function () {
    $admin = User::factory()->administrateur()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Ancien', 'prenom' => 'Nom']);
    $champ = ChampPersonnalise::factory()->create(['obligatoire' => false]);

    $response = $this->actingAs($admin)->patch(route('eleves.update', $eleve), [
        'nom' => 'Nouveau', 'prenom' => 'Prénom', 'sexe' => $eleve->sexe, 'date_naissance' => $eleve->date_naissance->format('Y-m-d'),
        'champs' => [$champ->id => 'Valeur mise à jour'],
    ]);

    $response->assertRedirect();
    expect($eleve->fresh()->nom)->toBe('Nouveau');
    $this->assertDatabaseHas('valeurs_champs_personnalises', [
        'eleve_id' => $eleve->id, 'champ_personnalise_id' => $champ->id, 'valeur' => 'Valeur mise à jour',
    ]);
});

test('administrators can archive and desarchiver a fiche élève', function () {
    $admin = User::factory()->administrateur()->create();
    $eleve = Eleve::factory()->create();

    $this->actingAs($admin)->patch(route('eleves.archiver', $eleve));
    expect($eleve->fresh()->statut)->toBe(StatutEleve::Archive);

    $this->actingAs($admin)->patch(route('eleves.desarchiver', $eleve));
    expect($eleve->fresh()->statut)->toBe(StatutEleve::Actif);
});

test('the eleve list is filtered server-side by search, classe, statut and date de création', function () {
    $admin = User::factory()->administrateur()->create();

    $match = Eleve::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Kokou']);
    $other = Eleve::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie']);
    $archived = Eleve::factory()->archive()->create(['nom' => 'Zannou', 'prenom' => 'Prince']);

    $bySearch = $this->actingAs($admin)->get(route('eleves.index', ['search' => 'Houngbo']));
    $bySearch->assertOk();
    $bySearch->assertSee('Houngbo Kokou');
    $bySearch->assertDontSee('Dossou Sylvie');

    $byStatut = $this->actingAs($admin)->get(route('eleves.index', ['statut' => 'archive']));
    $byStatut->assertSee('Zannou Prince');
    $byStatut->assertDontSee('Houngbo Kokou');

    expect($match->id)->not->toBeNull();
    expect($other->id)->not->toBeNull();
    expect($archived->id)->not->toBeNull();
});

test('a live-search (XMLHttpRequest) request to the eleve list returns just the table partial', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Kokou']);
    Eleve::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie']);

    $response = $this->actingAs($admin)->get(
        route('eleves.index', ['search' => 'Houngbo']),
        ['X-Requested-With' => 'XMLHttpRequest']
    );

    $response->assertOk();
    $response->assertSee('Houngbo Kokou');
    $response->assertDontSee('Dossou Sylvie');
    // Just the table fragment: no page shell/toolbar around it.
    $response->assertDontSee('<html', false);
    $response->assertDontSee('Rechercher par nom, prénom ou matricule', false);
});

test('a normal (non-XMLHttpRequest) request to the eleve list still returns the full page', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $response->assertSee('<html', false);
    $response->assertSee('Rechercher par nom, prénom ou matricule', false);
});

test('the eleve list is always sorted alphabetically by nom then prénom', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create(['nom' => 'Zinsou', 'prenom' => 'Prisca']);
    Eleve::factory()->create(['nom' => 'Adjovi', 'prenom' => 'Roméo']);
    Eleve::factory()->create(['nom' => 'Mensah', 'prenom' => 'Coffi']);

    $response = $this->actingAs($admin)->get(route('eleves.index'));

    $response->assertOk();
    $noms = $response->viewData('eleves')->pluck('nom')->all();

    expect($noms)->toBe(collect($noms)->sort(SORT_STRING)->values()->all());
});

test('the eleve list paginates at 50 per page and keeps active filters across pages', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->count(60)->create();

    $response = $this->actingAs($admin)->get(route('eleves.index', ['statut' => 'actif']));

    $response->assertOk();
    $paginator = $response->viewData('eleves');

    expect($paginator->perPage())->toBe(50);
    expect($paginator->total())->toBe(60);
    expect($paginator->nextPageUrl())->toContain('statut=actif');
});

test('the fiche endpoint returns identité, parcours and documents data', function () {
    $admin = User::factory()->administrateur()->create();
    $eleve = Eleve::factory()->create();

    $response = $this->actingAs($admin)->getJson(route('eleves.fiche', $eleve));

    $response->assertOk();
    $response->assertJsonStructure([
        'identite' => ['nom', 'prenom', 'matricule', 'sexe', 'date_naissance', 'statut', 'champs'],
        'parents',
        'parcours',
        'documents',
    ]);
});
