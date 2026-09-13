<?php

use App\Enums\NiveauQualitatif;
use App\Enums\ResultatMensuel;
use App\Enums\StatutBulletin;
use App\Enums\SystemeScolaire;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseDomaine;
use App\Models\DomaineEvaluation;
use App\Models\Eleve;
use App\Models\EvaluationDomaine;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\User;

function creerContexteEnseignantMaternelle(): array
{
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
    ]);
    $niveau = Niveau::factory()->maternelle()->create();
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeActive->id]);
    $domaine = DomaineEvaluation::factory()->create(['nom' => 'Langage']);
    $classeDomaine = ClasseDomaine::create(['classe_id' => $classe->id, 'domaine_evaluation_id' => $domaine->id]);

    $enseignant = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => null,
        'annee_academique_id' => $anneeActive->id,
        'est_professeur_principal' => true,
    ]);

    $eleve = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Maternelle,
        'date_examen' => now()->subDays(10)->toDateString(),
        'date_limite_saisie' => now()->addDays(5)->toDateString(),
    ]);

    return compact('anneeActive', 'niveau', 'classe', 'domaine', 'classeDomaine', 'enseignant', 'eleve', 'examen');
}

test('an assigned maternelle teacher can open the saisie de domaines screen', function () {
    ['classe' => $classe, 'domaine' => $domaine, 'enseignant' => $enseignant] = creerContexteEnseignantMaternelle();

    $response = $this->actingAs($enseignant)->get(route('enseignant.classes.show', $classe));

    $response->assertOk();
    $response->assertSee($domaine->nom);
});

test('a teacher not assigned to a maternelle classe cannot open its saisie de domaines', function () {
    ['classe' => $classe] = creerContexteEnseignantMaternelle();
    $autreEnseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($autreEnseignant)->get(route('enseignant.classes.show', $classe));

    $response->assertForbidden();
});

test('a batch of domaine evaluations can be saved in a single request', function () {
    ['classe' => $classe, 'domaine' => $domaine, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignantMaternelle();

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.domaines.batch-update', $classe), [
        'examen_id' => $examen->id,
        'evaluations' => [
            ['eleve_id' => $eleve->id, 'domaine_evaluation_id' => $domaine->id, 'valeur' => NiveauQualitatif::Satisfaisant->value, 'observation' => 'Bonne participation.'],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 1]);
    $this->assertDatabaseHas('evaluations_domaine', [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'valeur' => NiveauQualitatif::Satisfaisant->value,
        'observation' => 'Bonne participation.',
        'enseignant_id' => $enseignant->id,
    ]);
});

test('the observation on a domaine is optional', function () {
    ['classe' => $classe, 'domaine' => $domaine, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignantMaternelle();

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.domaines.batch-update', $classe), [
        'examen_id' => $examen->id,
        'evaluations' => [
            ['eleve_id' => $eleve->id, 'domaine_evaluation_id' => $domaine->id, 'valeur' => NiveauQualitatif::TresSatisfaisant->value, 'observation' => null],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 1]);
    $this->assertDatabaseHas('evaluations_domaine', [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'valeur' => NiveauQualitatif::TresSatisfaisant->value,
        'observation' => null,
    ]);
});

test('clearing both valeur and observation deletes the évaluation', function () {
    ['classe' => $classe, 'classeDomaine' => $classeDomaine, 'enseignant' => $enseignant, 'eleve' => $eleve, 'domaine' => $domaine, 'examen' => $examen] = creerContexteEnseignantMaternelle();
    EvaluationDomaine::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_domaine_id' => $classeDomaine->id,
        'examen_id' => $examen->id,
        'enseignant_id' => $enseignant->id,
        'valeur' => NiveauQualitatif::Satisfaisant,
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.domaines.batch-update', $classe), [
        'examen_id' => $examen->id,
        'evaluations' => [
            ['eleve_id' => $eleve->id, 'domaine_evaluation_id' => $domaine->id, 'valeur' => null, 'observation' => null],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 1]);
    $this->assertDatabaseMissing('evaluations_domaine', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id]);
});

test('a domaine batch save is rejected once the examen saisie deadline has passed', function () {
    ['classe' => $classe, 'domaine' => $domaine, 'enseignant' => $enseignant, 'eleve' => $eleve, 'anneeActive' => $anneeActive] = creerContexteEnseignantMaternelle();
    $examenPasse = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Maternelle,
        'date_examen' => now()->subDays(30)->toDateString(),
        'date_limite_saisie' => now()->subDays(20)->toDateString(),
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.domaines.batch-update', $classe), [
        'examen_id' => $examenPasse->id,
        'evaluations' => [
            ['eleve_id' => $eleve->id, 'domaine_evaluation_id' => $domaine->id, 'valeur' => NiveauQualitatif::Satisfaisant->value],
        ],
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('evaluations_domaine', ['eleve_id' => $eleve->id, 'examen_id' => $examenPasse->id]);
});

test('a domaine batch save skips a student whose bulletin is already validé', function () {
    ['classe' => $classe, 'domaine' => $domaine, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignantMaternelle();
    $inscription = Inscription::where('classe_id', $classe->id)->where('eleve_id', $eleve->id)->firstOrFail();
    Bulletin::factory()->create([
        'inscription_id' => $inscription->id,
        'examen_id' => $examen->id,
        'statut' => StatutBulletin::Valide,
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.domaines.batch-update', $classe), [
        'examen_id' => $examen->id,
        'evaluations' => [
            ['eleve_id' => $eleve->id, 'domaine_evaluation_id' => $domaine->id, 'valeur' => NiveauQualitatif::Satisfaisant->value],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 0]);
    $this->assertDatabaseMissing('evaluations_domaine', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id]);
});

test('the titulaire cannot validate a maternelle bulletin until every domaine has been evaluated', function () {
    ['classe' => $classe, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignantMaternelle();

    $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Bon mois.',
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'statut' => StatutBulletin::Brouillon->value]);
});

test('the titulaire can validate a maternelle bulletin once every domaine has been evaluated', function () {
    ['classe' => $classe, 'classeDomaine' => $classeDomaine, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignantMaternelle();
    EvaluationDomaine::create([
        'eleve_id' => $eleve->id,
        'classe_domaine_id' => $classeDomaine->id,
        'examen_id' => $examen->id,
        'enseignant_id' => $enseignant->id,
        'valeur' => NiveauQualitatif::Satisfaisant,
        'date_saisie' => now()->toDateString(),
    ]);

    $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Bon mois.',
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'statut' => 'valide']);
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'statut' => StatutBulletin::Valide->value]);
});
