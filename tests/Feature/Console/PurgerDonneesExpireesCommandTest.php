<?php

use App\Models\Eleve;
use App\Models\ParametreSysteme;

test('archived apprenants past the conservation duration are permanently deleted', function () {
    ParametreSysteme::factory()->create(['duree_conservation_donnees' => 12]);

    $expire = Eleve::factory()->create([
        'statut' => 'archive',
        'date_archivage' => now()->subMonths(13)->toDateString(),
    ]);
    $recent = Eleve::factory()->create([
        'statut' => 'archive',
        'date_archivage' => now()->subMonths(3)->toDateString(),
    ]);
    $actif = Eleve::factory()->create();

    $this->artisan('donnees:purger-expirees')->assertSuccessful();

    expect(Eleve::find($expire->id))->toBeNull();
    expect(Eleve::find($recent->id))->not->toBeNull();
    expect(Eleve::find($actif->id))->not->toBeNull();
});

test('purge is disabled when the conservation duration is 0', function () {
    ParametreSysteme::factory()->create(['duree_conservation_donnees' => 0]);

    $expire = Eleve::factory()->create([
        'statut' => 'archive',
        'date_archivage' => now()->subYears(5)->toDateString(),
    ]);

    $this->artisan('donnees:purger-expirees')->assertSuccessful();

    expect(Eleve::find($expire->id))->not->toBeNull();
});
