<?php

use App\Enums\StatutUtilisateur;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate with correct credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('mot-de-passe-secret'),
        'deux_fa_actif' => false,
        'doit_changer_mot_de_passe' => false,
    ]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'mot-de-passe-secret',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('users cannot authenticate with an incorrect password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('mot-de-passe-secret'),
    ]);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => $user->email,
        'password' => 'mauvais-mot-de-passe',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('archived accounts cannot authenticate', function () {
    $user = User::factory()->create([
        'password' => bcrypt('mot-de-passe-secret'),
        'statut' => StatutUtilisateur::Archive,
    ]);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => $user->email,
        'password' => 'mot-de-passe-secret',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users on their temporary password are redirected to the force-password screen', function () {
    $user = User::factory()->create([
        'password' => bcrypt('temporaire'),
        'deux_fa_actif' => false,
        'doit_changer_mot_de_passe' => true,
    ]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'temporaire',
    ]);

    $response->assertRedirect(route('password.force'));
});

test('users can set a new password on first login', function () {
    $user = User::factory()->create([
        'doit_changer_mot_de_passe' => true,
    ]);

    $response = $this->actingAs($user)->put(route('password.update'), [
        'password' => 'un-nouveau-mot-de-passe',
        'password_confirmation' => 'un-nouveau-mot-de-passe',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->doit_changer_mot_de_passe)->toBeFalse();
});

test('users with 2FA enabled are redirected to the challenge screen', function () {
    $user = User::factory()->create([
        'password' => bcrypt('mot-de-passe-secret'),
        'deux_fa_actif' => true,
        'secret_2fa' => app(TwoFactorAuthenticator::class)->generateSecretKey(),
        'doit_changer_mot_de_passe' => false,
    ]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'mot-de-passe-secret',
    ]);

    $response->assertRedirect(route('2fa.challenge'));
});

test('users can complete the 2FA challenge with a valid code', function () {
    $authenticator = app(TwoFactorAuthenticator::class);
    $secret = $authenticator->generateSecretKey();

    $user = User::factory()->create([
        'deux_fa_actif' => true,
        'secret_2fa' => $secret,
        'doit_changer_mot_de_passe' => false,
    ]);

    $validCode = (string) tap(new ReflectionMethod($authenticator, 'generateCode'), fn ($m) => $m->setAccessible(true))
        ->invoke($authenticator, $secret, (int) floor(time() / 30));

    $response = $this->actingAs($user)->post(route('2fa.verify'), [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('dashboard'));
});

test('users can log out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
