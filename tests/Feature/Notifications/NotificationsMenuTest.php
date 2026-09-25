<?php

use App\Models\Classe;
use App\Models\Examen;
use App\Models\User;
use App\Notifications\NotesIncompletesNotification;

test('a teacher sees their own notification and can mark it as read', function () {
    $enseignant = User::factory()->enseignant()->create();
    $classe = Classe::factory()->create();
    $examen = Examen::factory()->create();

    $enseignant->notify(new NotesIncompletesNotification($classe, $examen));
    $notification = $enseignant->notifications()->first();

    $response = $this->actingAs($enseignant)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Notes incomplètes');

    expect($notification->read_at)->toBeNull();

    $this->actingAs($enseignant)
        ->post(route('notifications.marquer-lu', $notification))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another user notification as read', function () {
    $enseignant = User::factory()->enseignant()->create();
    $autre = User::factory()->enseignant()->create();
    $classe = Classe::factory()->create();
    $examen = Examen::factory()->create();

    $enseignant->notify(new NotesIncompletesNotification($classe, $examen));
    $notification = $enseignant->notifications()->first();

    $this->actingAs($autre)
        ->post(route('notifications.marquer-lu', $notification))
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all as read clears every unread notification for the current user', function () {
    $enseignant = User::factory()->enseignant()->create();
    $classe = Classe::factory()->create();
    $examen = Examen::factory()->create();

    $enseignant->notify(new NotesIncompletesNotification($classe, $examen));

    $this->actingAs($enseignant)
        ->post(route('notifications.marquer-toutes-lues'))
        ->assertRedirect();

    expect($enseignant->unreadNotifications()->count())->toBe(0);
});
