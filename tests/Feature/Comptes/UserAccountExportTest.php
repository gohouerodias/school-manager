<?php

use App\Enums\ProfilUtilisateur;
use App\Exports\UsersExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

test('administrators can export the account list to excel', function () {
    $admin = User::factory()->administrateur()->create();
    User::factory()->enseignant()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('comptes.export.excel'));

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
});

test('administrators can export the account list to pdf', function () {
    $admin = User::factory()->administrateur()->create();
    User::factory()->enseignant()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('comptes.export.pdf'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('non-administrators cannot export the account list', function () {
    $enseignant = User::factory()->enseignant()->create();

    $this->actingAs($enseignant)->get(route('comptes.export.excel'))->assertForbidden();
    $this->actingAs($enseignant)->get(route('comptes.export.pdf'))->assertForbidden();
});

test('export links opt out of the full-page loader so it does not spin forever after a download', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->get(route('comptes.index'));

    $response->assertOk();
    $response->assertSee('href="'.route('comptes.export.excel').'" class="btn ghost" data-no-loader', false);
    $response->assertSee('href="'.route('comptes.export.pdf').'" class="btn ghost" data-no-loader', false);
});

test('the excel export only contains the accounts matching the active filters', function () {
    Excel::fake();

    $admin = User::factory()->administrateur()->create();
    $enseignant = User::factory()->enseignant()->create();
    $agent = User::factory()->create(['profil' => ProfilUtilisateur::AgentScolarite]);

    $this->actingAs($admin)
        ->get(route('comptes.export.excel', ['profil' => ProfilUtilisateur::Enseignant->value]))
        ->assertOk();

    Excel::assertDownloaded('comptes-utilisateurs.xlsx', function (UsersExport $export) use ($enseignant, $agent) {
        $emails = $export->collection()->pluck('email');

        return $emails->contains($enseignant->email) && ! $emails->contains($agent->email);
    });
});

test('the pdf export only contains the accounts matching the active filters', function () {
    $admin = User::factory()->administrateur()->create(['email' => 'admin.export@cscmadretrinidad.bj']);
    User::factory()->enseignant()->create(['email' => 'prof.export@cscmadretrinidad.bj']);

    Pdf::shouldReceive('loadView')
        ->once()
        ->withArgs(function (string $view, array $data) {
            $emails = $data['users']->pluck('email');

            return $view === 'comptes.export-pdf'
                && $emails->contains('prof.export@cscmadretrinidad.bj')
                && ! $emails->contains('admin.export@cscmadretrinidad.bj');
        })
        ->andReturnSelf();
    Pdf::shouldReceive('download')->once()->andReturn(response('pdf'));

    $this->actingAs($admin)
        ->get(route('comptes.export.pdf', ['search' => 'prof.export']))
        ->assertOk();
});

test('the export links carry the filters currently applied to the list', function () {
    $admin = User::factory()->administrateur()->create();
    $filters = ['search' => 'dossou', 'profil' => ProfilUtilisateur::Enseignant->value, 'statut' => 'actif'];

    $response = $this->actingAs($admin)->get(route('comptes.index', $filters));

    $response->assertOk();
    $response->assertSee('href="'.e(route('comptes.export.excel', $filters)).'"', false);
    $response->assertSee('href="'.e(route('comptes.export.pdf', $filters)).'"', false);
});
