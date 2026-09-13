<?php

use App\Enums\CycleNiveau;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\ParametreSysteme;
use App\Models\User;

/**
 * @return array{classe: Classe, inscription: Inscription, francais: ClasseMatiere, maths: ClasseMatiere}
 */
function setupClasseAvecMatieresPourRecalcul(): array
{
    $anneeAcademique = AnneeAcademique::factory()->create(['est_active' => true]);
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Primaire]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $eleve = Eleve::factory()->create();
    $inscription = Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $francais = ClasseMatiere::factory()->create([
        'classe_id' => $classe->id,
        'matiere_id' => Matiere::factory()->create(['nom' => 'Français'])->id,
        'coefficient' => 4,
    ]);
    $maths = ClasseMatiere::factory()->create([
        'classe_id' => $classe->id,
        'matiere_id' => Matiere::factory()->create(['nom' => 'Mathématiques'])->id,
        'coefficient' => 4,
    ]);

    return ['classe' => $classe, 'inscription' => $inscription, 'francais' => $francais, 'maths' => $maths];
}

test('calculerMoyenneAnnuelle returns null (not 0) when no bulletin mensuel is validé', function () {
    $inscription = Inscription::factory()->create();
    Bulletin::factory()->create(['inscription_id' => $inscription->id, 'moyenne_generale' => 12]); // Brouillon

    expect($inscription->calculerMoyenneAnnuelle())->toBeNull();
});

test('moyennesAnnuellesParMatiere averages each matière’s monthly averages across validated bulletins only', function () {
    ['inscription' => $inscription, 'francais' => $francais, 'maths' => $maths] = setupClasseAvecMatieresPourRecalcul();
    $eleveId = $inscription->eleve_id;

    $examenValide1 = Examen::factory()->create(['annee_academique_id' => $inscription->classe->annee_academique_id]);
    $examenValide2 = Examen::factory()->create(['annee_academique_id' => $inscription->classe->annee_academique_id]);
    $examenBrouillon = Examen::factory()->create(['annee_academique_id' => $inscription->classe->annee_academique_id]);

    Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'examen_id' => $examenValide1->id]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'examen_id' => $examenValide2->id]);
    Bulletin::factory()->create(['inscription_id' => $inscription->id, 'examen_id' => $examenBrouillon->id]); // Brouillon

    Note::factory()->create(['eleve_id' => $eleveId, 'classe_matiere_id' => $francais->id, 'examen_id' => $examenValide1->id, 'valeur' => 16]);
    Note::factory()->create(['eleve_id' => $eleveId, 'classe_matiere_id' => $francais->id, 'examen_id' => $examenValide2->id, 'valeur' => 14]);
    Note::factory()->create(['eleve_id' => $eleveId, 'classe_matiere_id' => $francais->id, 'examen_id' => $examenBrouillon->id, 'valeur' => 2]);

    Note::factory()->create(['eleve_id' => $eleveId, 'classe_matiere_id' => $maths->id, 'examen_id' => $examenValide1->id, 'valeur' => 10]);
    // Maths a une seule note pour l'un des deux mois validés : reste tout de même moyennée.

    $moyennes = $inscription->moyennesAnnuellesParMatiere();

    expect($moyennes)->toHaveCount(2);
    expect($moyennes->firstWhere('classe_matiere_id', $francais->id)['moyenne'])->toBe(15.0);
    expect($moyennes->firstWhere('classe_matiere_id', $maths->id)['moyenne'])->toBe(10.0);
});

test('the recalculer button on the bulletin annuel screen persists moyenne_annuelle and per-matière averages', function () {
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'inscription' => $inscription, 'francais' => $francais] = setupClasseAvecMatieresPourRecalcul();

    $examen = Examen::factory()->create(['annee_academique_id' => $classe->annee_academique_id]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'examen_id' => $examen->id, 'moyenne_generale' => 17]);
    Note::factory()->create(['eleve_id' => $inscription->eleve_id, 'classe_matiere_id' => $francais->id, 'examen_id' => $examen->id, 'valeur' => 18]);

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.annuel.recalculer'), ['classe_id' => $classe->id]);

    $response->assertRedirect();
    expect($inscription->fresh()->moyenne_annuelle)->toBe(17.0);
    $this->assertDatabaseHas('moyennes_annuelles_matieres', [
        'inscription_id' => $inscription->id,
        'classe_matiere_id' => $francais->id,
        'moyenne' => 18,
    ]);
});

test('recalculer backfills moyenne_generale on a bulletin déjà Validé mais jamais calculé, then uses it for the moyenne annuelle', function () {
    // Reproduit un bulletin Validé avant que Enseignant\
    // EspaceEnseignantController::validerBulletin() ne calcule et stocke
    // moyenne_generale à la validation : jusqu'ici, ce mois restait
    // silencieusement ignoré par Inscription::calculerMoyenneAnnuelle()
    // (avg() ignore les NULL), même si le bulletin était bien Validé.
    $admin = User::factory()->administrateur()->create();
    ['classe' => $classe, 'inscription' => $inscription, 'francais' => $francais, 'maths' => $maths] = setupClasseAvecMatieresPourRecalcul();

    $examen = Examen::factory()->create(['annee_academique_id' => $classe->annee_academique_id]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'examen_id' => $examen->id, 'moyenne_generale' => null]);
    Note::factory()->create(['eleve_id' => $inscription->eleve_id, 'classe_matiere_id' => $francais->id, 'examen_id' => $examen->id, 'valeur' => 16]);
    Note::factory()->create(['eleve_id' => $inscription->eleve_id, 'classe_matiere_id' => $maths->id, 'examen_id' => $examen->id, 'valeur' => 8]);

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.annuel.recalculer'), ['classe_id' => $classe->id]);

    $response->assertRedirect();
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'inscription_id' => $inscription->id, 'moyenne_generale' => 12]);
    expect($inscription->fresh()->moyenne_annuelle)->toBe(12.0);
});

test('recalculer is rejected for a classe de maternelle', function () {
    $admin = User::factory()->administrateur()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Maternelle]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->post(route('eleves.bulletins.annuel.recalculer'), ['classe_id' => $classe->id]);

    $response->assertNotFound();
});

test('the décisions de passage recalculer button persists moyennes for every non-maternelle classe of the année', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    ['classe' => $classe, 'inscription' => $inscription, 'francais' => $francais] = setupClasseAvecMatieresPourRecalcul();
    $anneeAcademique = $classe->anneeAcademique;

    $examen = Examen::factory()->create(['annee_academique_id' => $anneeAcademique->id]);
    Bulletin::factory()->valide()->create(['inscription_id' => $inscription->id, 'examen_id' => $examen->id, 'moyenne_generale' => 13]);
    Note::factory()->create(['eleve_id' => $inscription->eleve_id, 'classe_matiere_id' => $francais->id, 'examen_id' => $examen->id, 'valeur' => 12]);

    // Une classe de maternelle dans la même année : ne doit pas faire échouer le recalcul.
    $niveauMaternelle = Niveau::factory()->create(['cycle' => CycleNiveau::Maternelle]);
    Classe::factory()->create(['niveau_id' => $niveauMaternelle->id, 'annee_academique_id' => $anneeAcademique->id]);

    $response = $this->actingAs($admin)->post(route('academique.annees.decisions.recalculer', $anneeAcademique));

    $response->assertRedirect();
    expect($inscription->fresh()->moyenne_annuelle)->toBe(13.0);
    $this->assertDatabaseHas('moyennes_annuelles_matieres', [
        'inscription_id' => $inscription->id,
        'classe_matiere_id' => $francais->id,
        'moyenne' => 12,
    ]);
});

test('a non-administrateur cannot use the décisions de passage recalculer button', function () {
    $agent = User::factory()->agentScolarite()->create();
    $anneeAcademique = AnneeAcademique::factory()->create();

    $response = $this->actingAs($agent)->post(route('academique.annees.decisions.recalculer', $anneeAcademique));

    $response->assertForbidden();
});

test('the décisions de passage page shows "pas encore calculable" instead of a misleading 0 when nothing is validé yet', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $anneeAcademique = AnneeAcademique::factory()->create();
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Primaire]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeAcademique->id]);
    $inscription = Inscription::factory()->create(['classe_id' => $classe->id]);

    $response = $this->actingAs($admin)->get(route('academique.annees.decisions.index', $anneeAcademique));

    $response->assertOk();
    $response->assertSee('Pas encore calculable');
    // Le seuil ("Seuil de passage actuel : 10.00/20", voir le sous-titre de
    // la page) contient déjà la sous-chaîne "0.00/20" — on vérifie donc la
    // cellule "Moyenne annuelle" précisément plutôt qu'une simple recherche
    // de sous-chaîne sur toute la page.
    $response->assertSee('<td>—</td>', false);
});

test('overriding a décision with a null moyenne annuelle still requires a motif', function () {
    $admin = User::factory()->administrateur()->create();
    ParametreSysteme::factory()->create(['seuil_passage' => 10]);
    $inscription = Inscription::factory()->create();

    $response = $this->actingAs($admin)->from('/')->patch(route('academique.inscriptions.decision.update', $inscription), [
        'decision' => 'admis',
    ]);

    $response->assertSessionHasErrors('motif');
});
