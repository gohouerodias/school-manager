<?php

use App\Enums\DecisionAnnuelle;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;
use App\Services\PromotionAnnuelleService;

test('an élève admis is promoted to the same-lettered classe of the next niveau', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21, 'libelle' => 'CE2']);
    $cm1 = Niveau::factory()->create(['ordre' => 22, 'libelle' => 'CM1']);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id, 'nom' => 'CE2 A']);
    Classe::factory()->create(['niveau_id' => $cm1->id, 'annee_academique_id' => $anneeCible->id, 'nom' => 'CM1 B']);
    $classeCibleAttendue = Classe::factory()->create(['niveau_id' => $cm1->id, 'annee_academique_id' => $anneeCible->id, 'nom' => 'CM1 A']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Admis]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['promus'])->toBe(1);
    expect($rapport['redoublants'])->toBe(0);
    expect($rapport['non_resolus'])->toBe([]);
    $this->assertDatabaseHas('inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classeCibleAttendue->id]);
});

test('an élève admis falls back to the first classe of the next niveau when no section matches', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21, 'libelle' => 'CE2']);
    $cm1 = Niveau::factory()->create(['ordre' => 22, 'libelle' => 'CM1']);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id, 'nom' => 'CE2 A']);
    $classeCibleUnique = Classe::factory()->create(['niveau_id' => $cm1->id, 'annee_academique_id' => $anneeCible->id, 'nom' => 'CM1 Z']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Admis]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['promus'])->toBe(1);
    $this->assertDatabaseHas('inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classeCibleUnique->id]);
});

test('an élève admis in the last niveau is left unresolved (no niveau supérieur)', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $dernierNiveau = Niveau::factory()->create(['ordre' => 10, 'libelle' => '3e']);
    $classeSource = Classe::factory()->create(['niveau_id' => $dernierNiveau->id, 'annee_academique_id' => $anneeSource->id, 'nom' => '3e A']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Admis]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['promus'])->toBe(0);
    expect($rapport['non_resolus'])->toHaveCount(1);
    expect($rapport['non_resolus'][0]['eleve']->id)->toBe($eleve->id);
    expect(Inscription::where('eleve_id', $eleve->id)->count())->toBe(1);
});

test('an élève admis is left unresolved when the next niveau has no classe yet in the new année', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21, 'libelle' => 'CE2']);
    Niveau::factory()->create(['ordre' => 22, 'libelle' => 'CM1']);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id, 'nom' => 'CE2 A']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Admis]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['promus'])->toBe(0);
    expect($rapport['non_resolus'])->toHaveCount(1);
    expect(Inscription::where('eleve_id', $eleve->id)->count())->toBe(1);
});

test('a redoublant is reinscribed in the same niveau of the new année', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21, 'libelle' => 'CE2']);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id, 'nom' => 'CE2 A']);
    $classeCible = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeCible->id, 'nom' => 'CE2 A']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Redouble]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['promus'])->toBe(0);
    expect($rapport['redoublants'])->toBe(1);
    $this->assertDatabaseHas('inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classeCible->id]);
});

test('an élève exclu is left untouched and not reported', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21]);
    Niveau::factory()->create(['ordre' => 22]);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id]);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Exclu]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['promus'])->toBe(0);
    expect($rapport['redoublants'])->toBe(0);
    expect($rapport['non_resolus'])->toBe([]);
    expect(Inscription::where('eleve_id', $eleve->id)->count())->toBe(1);
});

test('an élève with no decision recorded yet is left untouched and not reported', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21]);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id]);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => null]);

    $rapport = (new PromotionAnnuelleService)->promouvoir($anneeSource, $anneeCible);

    expect($rapport['non_resolus'])->toBe([]);
    expect(Inscription::where('eleve_id', $eleve->id)->count())->toBe(1);
});

test('running the promotion twice does not create duplicate inscriptions', function () {
    $anneeSource = AnneeAcademique::factory()->create();
    $anneeCible = AnneeAcademique::factory()->create();
    $ce2 = Niveau::factory()->create(['ordre' => 21, 'libelle' => 'CE2']);
    $cm1 = Niveau::factory()->create(['ordre' => 22, 'libelle' => 'CM1']);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id, 'nom' => 'CE2 A']);
    Classe::factory()->create(['niveau_id' => $cm1->id, 'annee_academique_id' => $anneeCible->id, 'nom' => 'CM1 A']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Admis]);

    $service = new PromotionAnnuelleService;
    $premier = $service->promouvoir($anneeSource, $anneeCible);
    $second = $service->promouvoir($anneeSource, $anneeCible);

    expect($premier['promus'])->toBe(1);
    expect($second['promus'])->toBe(0);
    expect(Inscription::where('eleve_id', $eleve->id)->count())->toBe(2);
});

test('démarrer-ing an année runs the promotion and flips which année is active', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeSource = AnneeAcademique::factory()->create(['est_active' => true]);
    $anneeCible = AnneeAcademique::factory()->create(['est_active' => false]);
    $ce2 = Niveau::factory()->create(['ordre' => 21, 'libelle' => 'CE2']);
    $cm1 = Niveau::factory()->create(['ordre' => 22, 'libelle' => 'CM1']);
    $classeSource = Classe::factory()->create(['niveau_id' => $ce2->id, 'annee_academique_id' => $anneeSource->id, 'nom' => 'CE2 A']);
    $classeCible = Classe::factory()->create(['niveau_id' => $cm1->id, 'annee_academique_id' => $anneeCible->id, 'nom' => 'CM1 A']);
    $eleve = Eleve::factory()->create();
    Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $classeSource->id, 'date_inscription' => '2026-10-01', 'decision' => DecisionAnnuelle::Admis]);

    $response = $this->actingAs($admin)->post(route('academique.annees.demarrer', $anneeCible));

    $response->assertRedirect(route('academique.annees.show', $anneeCible));
    expect($anneeSource->fresh()->est_active)->toBeFalse();
    expect($anneeCible->fresh()->est_active)->toBeTrue();
    $this->assertDatabaseHas('inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classeCible->id]);
});

test('démarrer-ing an already-active année is rejected without re-running the promotion', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeActive = AnneeAcademique::factory()->create(['est_active' => true]);

    $response = $this->actingAs($admin)->post(route('academique.annees.demarrer', $anneeActive));

    $response->assertRedirect();
    expect($anneeActive->fresh()->est_active)->toBeTrue();
});

test('a non-administrateur cannot démarrer an année', function () {
    $agentScolarite = User::factory()->agentScolarite()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();

    $response = $this->actingAs($agentScolarite)->post(route('academique.annees.demarrer', $anneeAcademique));

    $response->assertForbidden();
});
