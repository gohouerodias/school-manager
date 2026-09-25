<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('the activation screen can be rendered', function () {
    $response = $this->get(route('compte.activer', ['token' => 'un-jeton']));

    $response->assertOk();
    $response->assertSee('Activer votre compte');
    $response->assertDontSee('Réinitialiser le mot de passe');
});

test('a freshly invited user (never logged in) can activate their account with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create(['derniere_connexion_at' => null]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        $response = $this->post(route('compte.activer.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'un-nouveau-mot-de-passe',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Votre compte a été activé. Vous pouvez maintenant vous connecter.');

        return true;
    });

    expect(Hash::check('un-nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

test('activating an account with an invalid token fails', function () {
    $user = User::factory()->create(['derniere_connexion_at' => null]);

    $response = $this->post(route('compte.activer.update'), [
        'token' => 'jeton-invalide',
        'email' => $user->email,
        'password' => 'un-nouveau-mot-de-passe',
        'password_confirmation' => 'un-nouveau-mot-de-passe',
    ]);

    $response->assertSessionHasErrors('email');
});
