<?php

use App\Enums\StatutEleve;
use App\Exports\ElevesExport;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\User;
use App\Support\EleveFilters;
use Illuminate\Http\Request;

test('EleveFilters::apply filters by search', function () {
    $match = Eleve::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Kokou']);
    Eleve::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie']);

    $result = EleveFilters::apply(Eleve::query(), Request::create('/', 'GET', ['search' => 'Houngbo']))->get();

    expect($result->pluck('id')->all())->toBe([$match->id]);
});

test('EleveFilters::apply filters by statut', function () {
    Eleve::factory()->create();
    $archived = Eleve::factory()->archive()->create();

    $result = EleveFilters::apply(Eleve::query(), Request::create('/', 'GET', ['statut' => 'archive']))->get();

    expect($result->pluck('id')->all())->toBe([$archived->id]);
});

test('EleveFilters::apply filters by classe (sans_classe)', function () {
    $sansClasse = Eleve::factory()->create();
    $avecClasse = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $avecClasse->id]);

    $result = EleveFilters::apply(Eleve::query(), Request::create('/', 'GET', ['classe' => 'sans_classe']))->get();

    expect($result->pluck('id')->all())->toBe([$sansClasse->id]);
});

test('the excel export only includes élèves matching the current filter', function () {
    Eleve::factory()->create(['nom' => 'Houngbo', 'prenom' => 'Kokou']);
    Eleve::factory()->create(['nom' => 'Dossou', 'prenom' => 'Sylvie']);

    $export = new ElevesExport(Request::create('/', 'GET', ['search' => 'Houngbo']));
    $collection = $export->collection();

    expect($collection)->toHaveCount(1);
    expect($collection->first()->nom)->toBe('Houngbo');
});

test('the excel export includes everyone when no filter is applied', function () {
    Eleve::factory()->count(3)->create();

    $export = new ElevesExport(Request::create('/', 'GET'));

    expect($export->collection())->toHaveCount(3);
});

test('exporting excel and pdf from the eleves list applies the active statut filter', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create();
    Eleve::factory()->archive()->create();

    $excel = $this->actingAs($admin)->get(route('eleves.export.excel', ['statut' => 'archive']));
    $excel->assertOk();
    $excel->assertHeader('content-disposition');

    $pdf = $this->actingAs($admin)->get(route('eleves.export.pdf', ['statut' => 'archive']));
    $pdf->assertOk();
    expect($pdf->headers->get('content-disposition'))->toContain('apprenants.pdf');
});

test('the export buttons on the eleves list carry the active filters in their href', function () {
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->archive()->create();

    $response = $this->actingAs($admin)->get(route('eleves.index', ['statut' => 'archive']));

    $response->assertOk();
    $response->assertSee('href="'.route('eleves.export.excel', ['statut' => 'archive']).'"', false);
    $response->assertSee('href="'.route('eleves.export.pdf', ['statut' => 'archive']).'"', false);
});

test('EleveFilters::apply filters by statut brouillon', function () {
    Eleve::factory()->create();
    $brouillon = Eleve::factory()->create(['statut' => StatutEleve::Brouillon]);

    $result = EleveFilters::apply(Eleve::query(), Request::create('/', 'GET', ['statut' => 'brouillon']))->get();

    expect($result->pluck('id')->all())->toBe([$brouillon->id]);
});

test('a brouillon fiche (no nom/prénom/date de naissance yet) does not break the excel or pdf export', function () {
    // Regression test: ElevesExport::map() used to call ->format() directly
    // on date_naissance, which is fatal once StatutEleve::Brouillon rows
    // (whose fields may still be null) can reach the export.
    $admin = User::factory()->administrateur()->create();
    Eleve::factory()->create([
        'matricule' => null,
        'nom' => null,
        'prenom' => null,
        'sexe' => null,
        'date_naissance' => null,
        'statut' => StatutEleve::Brouillon,
    ]);

    $excel = new ElevesExport(Request::create('/', 'GET'));
    expect($excel->map($excel->collection()->first()))->toBe(['—', '—', '—', '—', '—', 'Sans classe', 'Brouillon']);

    $this->actingAs($admin)->get(route('eleves.export.pdf'))->assertOk();
    $this->actingAs($admin)->get(route('eleves.export.excel'))->assertOk();
});
