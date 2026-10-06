<?php

use App\Jobs\GenererBulletinsAnnuelsClasseJob;
use App\Jobs\GenererBulletinsClasseJob;
use App\Models\DemandeGenerationBulletin;
use App\Models\DemandeGenerationBulletinAnnuel;
use Illuminate\Support\Facades\Bus;

test('without a queue worker (default), bulletin generation starts right after the response', function () {
    config(['queue.bulletins_en_file_attente' => false]);
    Bus::fake();

    GenererBulletinsClasseJob::lancer(DemandeGenerationBulletin::factory()->create());
    GenererBulletinsAnnuelsClasseJob::lancer(DemandeGenerationBulletinAnnuel::factory()->create());

    Bus::assertDispatchedAfterResponse(GenererBulletinsClasseJob::class);
    Bus::assertDispatchedAfterResponse(GenererBulletinsAnnuelsClasseJob::class);
});

test('with BULLETINS_FILE_ATTENTE on, bulletin generation goes through the queue', function () {
    config(['queue.bulletins_en_file_attente' => true]);
    Bus::fake();

    GenererBulletinsClasseJob::lancer(DemandeGenerationBulletin::factory()->create());
    GenererBulletinsAnnuelsClasseJob::lancer(DemandeGenerationBulletinAnnuel::factory()->create());

    Bus::assertDispatched(GenererBulletinsClasseJob::class);
    Bus::assertDispatched(GenererBulletinsAnnuelsClasseJob::class);
    Bus::assertNotDispatchedAfterResponse(GenererBulletinsClasseJob::class);
});
