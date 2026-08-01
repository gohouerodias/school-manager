<?php

use App\Enums\StatutEleve;
use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\Eleve;
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
