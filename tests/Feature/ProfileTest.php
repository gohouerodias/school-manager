<?php

use App\Models\User;

test('a user can update their own name and telephone', function () {
    $user = User::factory()->enseignant()->create([
        'name' => 'Ancien Nom',
        'telephone' => '+229 00 00 00 00',
    ]);

    $response = $this->actingAs($user)->patch(route('profil.update'), [
        'name' => 'Kokou Houngbo',
        'telephone' => '+229 12 34 56 78',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->name)->toBe('Kokou Houngbo');
    expect($user->telephone)->toBe('+229 12 34 56 78');
});

test('a user cannot change their own e-mail via the self-service profile form', function () {
    $user = User::factory()->enseignant()->create(['email' => 'original@cscmadretrinidad.bj']);

    $this->actingAs($user)->patch(route('profil.update'), [
        'name' => $user->name,
        'telephone' => $user->telephone,
        'email' => 'usurpe@cscmadretrinidad.bj',
    ]);

    expect($user->fresh()->email)->toBe('original@cscmadretrinidad.bj');
});

test('a name is required to update the self-service profile', function () {
    $user = User::factory()->enseignant()->create();

    $response = $this->actingAs($user)->from(route('dashboard'))->patch(route('profil.update'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
});
