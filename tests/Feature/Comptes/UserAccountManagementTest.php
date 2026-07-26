<?php

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

test('non-administrators cannot access account management', function () {
    $enseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($enseignant)->get(route('comptes.index'));

    $response->assertForbidden();
});

test('administrators can view the account list', function () {
    $admin = User::factory()->administrateur()->create();
    User::factory()->enseignant()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('comptes.index'));

    $response->assertOk();
});

test('administrators can invite several accounts at once', function () {
    Notification::fake();

    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->post(route('comptes.store'), [
        'invites' => [
            ['email' => 'nouveau1@cscmadretrinidad.bj', 'name' => 'Roméo Adjovi', 'telephone' => '+229 01 02 03 04', 'profil' => ProfilUtilisateur::Enseignant->value],
            ['email' => 'nouveau2@cscmadretrinidad.bj', 'name' => 'Sylvie Dossou', 'telephone' => '+229 05 06 07 08', 'profil' => ProfilUtilisateur::AgentScolarite->value],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', ['email' => 'nouveau1@cscmadretrinidad.bj', 'profil' => 'enseignant', 'name' => 'Roméo Adjovi', 'telephone' => '+229 01 02 03 04']);
    $this->assertDatabaseHas('users', ['email' => 'nouveau2@cscmadretrinidad.bj', 'profil' => 'agent_scolarite', 'name' => 'Sylvie Dossou']);

    $created = User::where('email', 'nouveau1@cscmadretrinidad.bj')->firstOrFail();
    expect($created->doit_changer_mot_de_passe)->toBeTrue();

    Notification::assertSentTimes(ResetPasswordNotification::class, 2);
});

test('inviting an already-registered email fails validation', function () {
    $admin = User::factory()->administrateur()->create();
    $existing = User::factory()->create();

    $response = $this->actingAs($admin)->from(route('comptes.index'))->post(route('comptes.store'), [
        'invites' => [
            ['email' => $existing->email, 'name' => 'Coffi Zannou', 'telephone' => '+229 01 02 03 04', 'profil' => ProfilUtilisateur::Enseignant->value],
        ],
    ]);

    $response->assertSessionHasErrors('invites.0.email');
});

test('inviting without a name or telephone fails validation', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->from(route('comptes.index'))->post(route('comptes.store'), [
        'invites' => [
            ['email' => 'sans.infos@cscmadretrinidad.bj', 'name' => '', 'telephone' => '', 'profil' => ProfilUtilisateur::Enseignant->value],
        ],
    ]);

    $response->assertSessionHasErrors(['invites.0.name', 'invites.0.telephone']);
});

test('administrators can update another user\'s name, telephone and email', function () {
    $admin = User::factory()->administrateur()->create();
    $target = User::factory()->enseignant()->create();

    $response = $this->actingAs($admin)->patch(route('comptes.update', $target), [
        'name' => 'Emmanuel Kpossou',
        'telephone' => '+229 22 33 44 55',
        'email' => 'emmanuel.kpossou@cscmadretrinidad.bj',
    ]);

    $response->assertRedirect();
    $target->refresh();
    expect($target->name)->toBe('Emmanuel Kpossou');
    expect($target->telephone)->toBe('+229 22 33 44 55');
    expect($target->email)->toBe('emmanuel.kpossou@cscmadretrinidad.bj');
});

test('updating a user with an e-mail already used by another account fails validation', function () {
    $admin = User::factory()->administrateur()->create();
    $target = User::factory()->enseignant()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($admin)->from(route('comptes.index'))->patch(route('comptes.update', $target), [
        'name' => $target->name,
        'telephone' => $target->telephone,
        'email' => $other->email,
    ]);

    $response->assertSessionHasErrors('email');
});

test('non-administrators cannot update another user\'s account', function () {
    $enseignant = User::factory()->enseignant()->create();
    $target = User::factory()->enseignant()->create();

    $response = $this->actingAs($enseignant)->patch(route('comptes.update', $target), [
        'name' => 'Nouveau Nom',
        'telephone' => '+229 00 00 00 00',
        'email' => 'autre@cscmadretrinidad.bj',
    ]);

    $response->assertForbidden();
});

test('administrators can archive and reactivate an account', function () {
    $admin = User::factory()->administrateur()->create();
    $target = User::factory()->enseignant()->create();

    $this->actingAs($admin)->patch(route('comptes.archiver', $target));
    expect($target->fresh()->statut)->toBe(StatutUtilisateur::Archive);

    $this->actingAs($admin)->patch(route('comptes.reactiver', $target));
    expect($target->fresh()->statut)->toBe(StatutUtilisateur::Actif);
});

test('the account list is filtered server-side by search, profil and statut', function () {
    $admin = User::factory()->administrateur()->create();
    $match = User::factory()->enseignant()->create(['name' => 'Kokou Houngbo', 'email' => 'k.houngbo@cscmadretrinidad.bj']);
    $otherProfil = User::factory()->direction()->create(['name' => 'Sylvie Dossou']);
    $archived = User::factory()->enseignant()->create(['name' => 'Roméo Adjovi', 'statut' => StatutUtilisateur::Archive]);

    $bySearch = $this->actingAs($admin)->get(route('comptes.index', ['search' => 'Houngbo']));
    $bySearch->assertOk();
    $bySearch->assertSee('Kokou Houngbo');
    $bySearch->assertDontSee('Sylvie Dossou');
    $bySearch->assertDontSee('Roméo Adjovi');

    $byProfil = $this->actingAs($admin)->get(route('comptes.index', ['profil' => ProfilUtilisateur::Direction->value]));
    $byProfil->assertSee('Sylvie Dossou');
    $byProfil->assertDontSee('Kokou Houngbo');

    $byStatut = $this->actingAs($admin)->get(route('comptes.index', ['statut' => 'archive']));
    $byStatut->assertSee('Roméo Adjovi');
    $byStatut->assertDontSee('Kokou Houngbo');
    $byStatut->assertDontSee('Sylvie Dossou');

    expect($match->id)->not->toBeNull();
    expect($otherProfil->id)->not->toBeNull();
    expect($archived->id)->not->toBeNull();
});

test('the account list is always sorted alphabetically by name, filtered or not', function () {
    $admin = User::factory()->administrateur()->create();
    User::factory()->direction()->create(['name' => 'Zinsou Prisca']);
    User::factory()->enseignant()->create(['name' => 'Adjovi Roméo']);
    User::factory()->agentScolarite()->create(['name' => 'Mensah Coffi']);

    $response = $this->actingAs($admin)->get(route('comptes.index'));

    $response->assertOk();
    $names = $response->viewData('users')->pluck('name')->all();

    expect($names)->toBe(collect($names)->sort(SORT_STRING)->values()->all());
});

test('the account list paginates at 50 per page and keeps active filters across pages', function () {
    $admin = User::factory()->administrateur()->create();
    User::factory()->enseignant()->count(60)->create();

    $response = $this->actingAs($admin)->get(route('comptes.index', ['profil' => ProfilUtilisateur::Enseignant->value]));

    $response->assertOk();
    $paginator = $response->viewData('users');

    expect($paginator->perPage())->toBe(50);
    expect($paginator->total())->toBe(60);
    expect($paginator->nextPageUrl())->toContain('profil='.ProfilUtilisateur::Enseignant->value);
});
