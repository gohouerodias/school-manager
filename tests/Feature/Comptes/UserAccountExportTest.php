<?php

use App\Models\User;

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
