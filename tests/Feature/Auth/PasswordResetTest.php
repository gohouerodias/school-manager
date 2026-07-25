<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('forgot password screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('a reset link is sent when requesting a password reset for an existing account', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post(route('password.email'), ['email' => $user->email]);

    $response->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('the same generic message is shown for an unknown email, avoiding account enumeration', function () {
    Notification::fake();

    $response = $this->post(route('password.email'), ['email' => 'inconnu@cscmadretrinidad.bj']);

    $response->assertSessionHas('status');
    Notification::assertNothingSent();
});

test('users can reset their password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        $response = $this->post(route('password.reset.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'un-nouveau-mot-de-passe',
        ]);

        $response->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('un-nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

test('resetting a password with an invalid token fails', function () {
    $user = User::factory()->create();

    $response = $this->from(route('password.reset', ['token' => 'jeton-invalide']))
        ->post(route('password.reset.update'), [
            'token' => 'jeton-invalide',
            'email' => $user->email,
            'password' => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'un-nouveau-mot-de-passe',
        ]);

    $response->assertSessionHasErrors('email');
});
