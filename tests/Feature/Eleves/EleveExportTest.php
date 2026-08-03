<?php

use App\Exports\ElevesExport;
use App\Models\Eleve;
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
    \App\Models\Inscription::factory()->create(['eleve_id' => $avecClasse->id]);

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
