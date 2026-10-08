<?php

use App\Enums\CycleNiveau;
use App\Enums\ProfilUtilisateur;
use App\Models\Niveau;
use App\Models\TypeDocument;
use App\Models\User;

test('the certificat de scolarité is no longer required, the bulletin still is for a transfer', function () {
    $certificat = TypeDocument::query()->where('libelle', 'Certificat de scolarité antérieure')->firstOrFail();
    $bulletin = TypeDocument::query()->where('libelle', "Bulletin de l'école précédente")->firstOrFail();

    expect($certificat->obligatoire)->toBeFalse()
        ->and($certificat->requis_si_transfert)->toBeFalse()
        ->and($bulletin->requis_si_transfert)->toBeTrue();
});

test('the Pré-maternelle niveau comes right before Maternelle 1, as a maternelle first-schooling niveau', function () {
    $preMaternelle = Niveau::query()->where('libelle', 'Pré-maternelle')->firstOrFail();
    $maternelle1 = Niveau::query()->where('libelle', 'Maternelle 1')->firstOrFail();

    expect($preMaternelle->cycle)->toBe(CycleNiveau::Maternelle)
        ->and($preMaternelle->premiere_scolarisation)->toBeTrue()
        ->and($preMaternelle->ordre)->toBe($maternelle1->ordre - 1)
        ->and(Niveau::query()->orderBy('ordre')->first()->is($preMaternelle))->toBeTrue();
});

test('the agent de scolarité profile is now shown as « Secrétariat »', function () {
    expect(ProfilUtilisateur::AgentScolarite->label())->toBe('Secrétariat');
});

test('the tuteurs menu and page are now called « Liste des parents »', function () {
    $admin = User::factory()->administrateur()->create();

    $this->actingAs($admin)->get(route('tuteurs.index'))
        ->assertOk()
        ->assertSee('Liste des parents')
        ->assertDontSee('Liste des tuteurs');
});
