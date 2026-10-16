<?php

use App\Enums\StatutUtilisateur;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

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

test('the first-login hint is only shown on the force-password screen, not on every login', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('toute première connexion');

    $user = User::factory()->create([
        'doit_changer_mot_de_passe' => true,
    ]);

    $this->actingAs($user)->get(route('password.force'))
        ->assertOk()
        ->assertSee('toute première connexion');
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

test('login attempts are limited to protect against password guessing', function () {
    $user = User::factory()->create(['password' => bcrypt('mot-de-passe-secret')]);

    foreach (range(1, 5) as $tentative) {
        $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);
    }

    // 6e tentative dans la minute : refusée même avec le bon mot de passe.
    $response = $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'mot-de-passe-secret']);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Trop de tentatives');
    $this->assertGuest();
});

test('forgot-password requests are limited too', function () {
    foreach (range(1, 6) as $tentative) {
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'inconnu@exemple.bj']);
    }

    $this->from(route('password.request'))->post(route('password.email'), ['email' => 'inconnu@exemple.bj'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Trop de tentatives');
});

test('every page is sent with the security headers', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('an expired session sends back to the login page with an explanation', function () {
    $response = $this->withCookie(config('session.cookie'), 'ancienne-session')
        ->get(route('eleves.index'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('session_expiree', true);

    $this->withSession(['session_expiree' => true])->get(route('login'))
        ->assertOk()
        ->assertSee('Votre session a expiré');
});

test('a first visit to a protected page goes to login without the expiry message', function () {
    $this->get(route('eleves.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('session_expiree', false);
});

test('a background request with an expired session gets a 401 that the page turns into a reload', function () {
    $this->getJson(route('eleves.index'))
        ->assertUnauthorized()
        ->assertJsonFragment(['message' => "Votre session a expiré après une longue période d'inactivité. Reconnectez-vous pour continuer."]);
});

test('an expired CSRF token (419) sends back to the login page instead of an error page', function () {
    Route::middleware('web')->post('/test-jeton-expire', fn () => throw new TokenMismatchException);

    $this->post('/test-jeton-expire')
        ->assertRedirect(route('login'))
        ->assertSessionHas('session_expiree', true);

    $this->postJson('/test-jeton-expire')->assertStatus(419);
});

test('logged-in pages tell the browser how long the session lasts', function () {
    $user = User::factory()->administrateur()->create();

    $this->actingAs($user)->get(route('eleves.index'))
        ->assertSee('<meta name="session-lifetime" content="'.config('session.lifetime').'">', false);
});
