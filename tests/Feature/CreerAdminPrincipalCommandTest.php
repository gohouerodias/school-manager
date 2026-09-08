<?php

use App\Enums\ProfilUtilisateur;
use App\Models\User;

test('creates the sole administrateur account with a forced password change', function () {
    $this->artisan('admin:creer-principal', [
        'name' => 'Admin CSC',
        'email' => 'admin@cscmadretrinidad.bj',
        'telephone' => '+229 00 00 00 00',
    ])->assertSuccessful();

    $admin = User::where('email', 'admin@cscmadretrinidad.bj')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->profil)->toBe(ProfilUtilisateur::Administrateur)
        ->and($admin->doit_changer_mot_de_passe)->toBeTrue()
        ->and(User::count())->toBe(1);
});

test('refuses to run twice — a second administrateur must be invited from the app instead', function () {
    $this->artisan('admin:creer-principal', [
        'name' => 'Admin CSC',
        'email' => 'admin@cscmadretrinidad.bj',
        'telephone' => '+229 00 00 00 00',
    ])->assertSuccessful();

    $this->artisan('admin:creer-principal', [
        'name' => 'Second Admin',
        'email' => 'autre@cscmadretrinidad.bj',
        'telephone' => '+229 00 00 00 01',
    ])->assertFailed();

    expect(User::count())->toBe(1);
});

test('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'admin@cscmadretrinidad.bj']);

    $this->artisan('admin:creer-principal', [
        'name' => 'Admin CSC',
        'email' => 'admin@cscmadretrinidad.bj',
        'telephone' => '+229 00 00 00 00',
    ])->assertFailed();
});
