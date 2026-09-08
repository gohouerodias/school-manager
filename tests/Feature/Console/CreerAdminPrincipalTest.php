<?php

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates the principal administrator account with the given options', function () {
    $this->artisan('admin:creer-principal', [
        '--email' => 'admin@cscmadretrinidad.bj',
        '--name' => 'Admin CSC',
        '--password' => 'un-mot-de-passe-temporaire',
    ])->assertSuccessful();

    $admin = User::where('email', 'admin@cscmadretrinidad.bj')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Admin CSC')
        ->and($admin->profil)->toBe(ProfilUtilisateur::Administrateur)
        ->and($admin->statut)->toBe(StatutUtilisateur::Actif)
        ->and($admin->doit_changer_mot_de_passe)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('un-mot-de-passe-temporaire', $admin->password))->toBeTrue();
});

test('defaults name to Administrateur and password to "password" when omitted', function () {
    $this->artisan('admin:creer-principal', [
        '--email' => 'admin@cscmadretrinidad.bj',
    ])->assertSuccessful();

    $admin = User::where('email', 'admin@cscmadretrinidad.bj')->first();

    expect($admin->name)->toBe('Administrateur')
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});

test('fails without an email', function () {
    $this->artisan('admin:creer-principal')->assertFailed();

    expect(User::count())->toBe(0);
});

test('fails when a user with that email already exists', function () {
    User::factory()->create(['email' => 'admin@cscmadretrinidad.bj']);

    $this->artisan('admin:creer-principal', [
        '--email' => 'admin@cscmadretrinidad.bj',
    ])->assertFailed();

    expect(User::count())->toBe(1);
});
