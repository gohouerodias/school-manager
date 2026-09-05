<?php

use App\Enums\CycleNiveau;
use App\Enums\ResultatMensuel;
use App\Enums\StatutBulletin;
use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Models\AffectationEnseignant;
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
use App\Models\User;

function creerContexteEnseignant(): array
{
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
    ]);
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Primaire]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeActive->id]);
    $matiere = Matiere::factory()->create();
    ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'coefficient' => 4]);

    $enseignant = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $enseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
        'annee_academique_id' => $anneeActive->id,
        'est_professeur_principal' => true,
    ]);

    $eleve = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(10)->toDateString(),
        'date_limite_saisie' => now()->addDays(5)->toDateString(),
    ]);

    return compact('anneeActive', 'niveau', 'classe', 'matiere', 'enseignant', 'eleve', 'examen');
}

test('a teacher sees only their own classes', function () {
    ['classe' => $classe, 'enseignant' => $enseignant] = creerContexteEnseignant();
    // ClasseFactory's default `nom` is a single letter (A/B/C) — too short to
    // assert "not seen" reliably (it can appear anywhere else on the page,
    // e.g. in "CSCMT" or a teacher's initials). Give it a distinctive name
    // for this specific assertion.
    $classe->update(['nom' => 'CM2-ZZTEST']);
    $autreEnseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($enseignant)->get(route('enseignant.classes.index'));
    $response->assertOk();
    $response->assertSee($classe->nom);

    $response2 = $this->actingAs($autreEnseignant)->get(route('enseignant.classes.index'));
    $response2->assertOk();
    $response2->assertDontSee($classe->nom);
});

test('a teacher not assigned to a classe cannot open its saisie de notes', function () {
    ['classe' => $classe] = creerContexteEnseignant();
    $autreEnseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($autreEnseignant)->get(route('enseignant.classes.show', $classe));

    $response->assertForbidden();
});

test('an assigned teacher can create a note', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $matiere->id,
        'examen_id' => $examen->id,
        'valeur' => 15.5,
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'valeur' => 15.5]);
    $this->assertDatabaseHas('notes', [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'valeur' => 15.5,
        'enseignant_id' => $enseignant->id,
    ]);
});

test('clearing a note value deletes it', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $classeMatiere = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $matiere->id)->first();
    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_matiere_id' => $classeMatiere->id,
        'examen_id' => $examen->id,
        'enseignant_id' => $enseignant->id,
        'type' => TypeEvaluation::EvaluationMensuelle,
        'numero' => 1,
        'valeur' => 12,
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $matiere->id,
        'examen_id' => $examen->id,
        'valeur' => null,
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'deleted' => true]);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id]);
});

test('a teacher cannot create a note for a matière they are not assigned', function () {
    ['classe' => $classe, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $autreMatiere = Matiere::factory()->create();
    ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $autreMatiere->id, 'coefficient' => 2]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $autreMatiere->id,
        'examen_id' => $examen->id,
        'valeur' => 10,
    ]);

    $response->assertForbidden();
});

test('a note cannot be created once the examen saisie deadline has passed', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'anneeActive' => $anneeActive] = creerContexteEnseignant();
    $examenPasse = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(30)->toDateString(),
        'date_limite_saisie' => now()->subDays(20)->toDateString(),
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $matiere->id,
        'examen_id' => $examenPasse->id,
        'valeur' => 10,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examenPasse->id]);
});

test('an assigned teacher can leave a subject comment', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.commentaires-matiere.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $matiere->id,
        'examen_id' => $examen->id,
        'commentaire' => 'Bonne participation en classe.',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
    $this->assertDatabaseHas('commentaires_matiere', [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'commentaire' => 'Bonne participation en classe.',
    ]);
});

test('the titulaire can write the monthly bulletin comment', function () {
    ['classe' => $classe, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Travail sérieux ce mois-ci.',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
    $this->assertDatabaseHas('bulletins', [
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Travail sérieux ce mois-ci.',
    ]);
});

test('a non-titulaire teacher cannot write the monthly bulletin comment', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'eleve' => $eleve, 'examen' => $examen, 'anneeActive' => $anneeActive] = creerContexteEnseignant();

    $autreEnseignant = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $autreEnseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
        'annee_academique_id' => $anneeActive->id,
        'est_professeur_principal' => false,
    ]);

    $response = $this->actingAs($autreEnseignant)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Tentative non autorisée.',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('bulletins', ['examen_id' => $examen->id, 'appreciation' => 'Tentative non autorisée.']);
});

test('the titulaire can validate a bulletin that already has a brouillon', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $classeMatiere = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $matiere->id)->first();
    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_matiere_id' => $classeMatiere->id,
        'examen_id' => $examen->id,
        'enseignant_id' => $enseignant->id,
        'type' => TypeEvaluation::EvaluationMensuelle,
        'numero' => 1,
        'valeur' => 14,
    ]);

    $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Travail sérieux ce mois-ci.',
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'statut' => 'valide']);
    $this->assertDatabaseHas('bulletins', [
        'examen_id' => $examen->id,
        'statut' => StatutBulletin::Valide->value,
        'valide_par_id' => $enseignant->id,
    ]);
});

test('validating a bulletin with no brouillon yet is rejected', function () {
    ['classe' => $classe, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('bulletins', ['examen_id' => $examen->id]);
});

test('a non-titulaire cannot validate a bulletin', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'eleve' => $eleve, 'examen' => $examen, 'anneeActive' => $anneeActive] = creerContexteEnseignant();
    $autreEnseignant = User::factory()->enseignant()->create();
    AffectationEnseignant::create([
        'enseignant_id' => $autreEnseignant->id,
        'classe_id' => $classe->id,
        'matiere_id' => $matiere->id,
        'annee_academique_id' => $anneeActive->id,
        'est_professeur_principal' => false,
    ]);

    $response = $this->actingAs($autreEnseignant)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertForbidden();
});

test('once validated, notes can no longer be modified until dévalidation', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $classeMatiere = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $matiere->id)->first();
    $inscription = Inscription::where('eleve_id', $eleve->id)->where('classe_id', $classe->id)->first();
    Bulletin::factory()->create([
        'inscription_id' => $inscription->id,
        'examen_id' => $examen->id,
    ])->update(['statut' => StatutBulletin::Valide, 'valide_par_id' => $enseignant->id, 'valide_at' => now()]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $matiere->id,
        'examen_id' => $examen->id,
        'valeur' => 15,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id]);
});

test('once validated, the monthly bulletin comment can no longer be modified until dévalidation', function () {
    ['classe' => $classe, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $inscription = Inscription::where('eleve_id', $eleve->id)->where('classe_id', $classe->id)->first();
    Bulletin::factory()->create([
        'inscription_id' => $inscription->id,
        'examen_id' => $examen->id,
        'appreciation' => 'Déjà signé.',
    ])->update(['statut' => StatutBulletin::Valide, 'valide_par_id' => $enseignant->id, 'valide_at' => now()]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Mal->value,
        'appreciation' => 'Tentative de modification après signature.',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'appreciation' => 'Déjà signé.']);
});

test('the titulaire can dévalider a bulletin, unlocking notes again', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $inscription = Inscription::where('eleve_id', $eleve->id)->where('classe_id', $classe->id)->first();
    Bulletin::factory()->create([
        'inscription_id' => $inscription->id,
        'examen_id' => $examen->id,
    ])->update(['statut' => StatutBulletin::Valide, 'valide_par_id' => $enseignant->id, 'valide_at' => now()]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.bulletins.devalider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);
    $response->assertOk()->assertJson(['ok' => true, 'statut' => 'brouillon']);
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'statut' => StatutBulletin::Brouillon->value, 'valide_par_id' => null]);

    $noteResponse = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $matiere->id,
        'examen_id' => $examen->id,
        'valeur' => 14,
    ]);
    $noteResponse->assertOk();
});

test('an assigned teacher can export their classe’s notes to Excel', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $classeMatiere = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $matiere->id)->first();
    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_matiere_id' => $classeMatiere->id,
        'examen_id' => $examen->id,
        'enseignant_id' => $enseignant->id,
        'type' => TypeEvaluation::EvaluationMensuelle,
        'numero' => 1,
        'valeur' => 16,
    ]);

    $response = $this->actingAs($enseignant)->get(route('enseignant.classes.notes.export', ['classe' => $classe, 'examen_id' => $examen->id]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('a teacher not assigned to a classe cannot export its notes', function () {
    ['classe' => $classe] = creerContexteEnseignant();
    $autreEnseignant = User::factory()->enseignant()->create();

    $response = $this->actingAs($autreEnseignant)->get(route('enseignant.classes.notes.export', $classe));

    $response->assertForbidden();
});

test('the saisie de notes shows each matière’s coefficient', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant] = creerContexteEnseignant();

    $response = $this->actingAs($enseignant)->get(route('enseignant.classes.show', $classe));

    $response->assertOk();
    $response->assertSee($matiere->nom);
    $response->assertSee('Coef 4');
});

test('a batch of note changes can be saved in a single request', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $autreEleve = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $autreEleve->id, 'classe_id' => $classe->id]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.batch-update', $classe), [
        'examen_id' => $examen->id,
        'notes' => [
            ['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => 14],
            ['eleve_id' => $autreEleve->id, 'matiere_id' => $matiere->id, 'valeur' => 8.5],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 2]);
    $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id, 'valeur' => 14]);
    $this->assertDatabaseHas('notes', ['eleve_id' => $autreEleve->id, 'examen_id' => $examen->id, 'valeur' => 8.5]);
});

test('a batch save can clear a note by sending a null valeur', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $classeMatiere = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $matiere->id)->first();
    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_matiere_id' => $classeMatiere->id,
        'examen_id' => $examen->id,
        'enseignant_id' => $enseignant->id,
        'type' => TypeEvaluation::EvaluationMensuelle,
        'numero' => 1,
        'valeur' => 12,
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.batch-update', $classe), [
        'examen_id' => $examen->id,
        'notes' => [
            ['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => null],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 1]);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id]);
});

test('a batch save silently ignores an entry for a matière the teacher is not assigned to', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $autreMatiere = Matiere::factory()->create();
    ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $autreMatiere->id, 'coefficient' => 2]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.batch-update', $classe), [
        'examen_id' => $examen->id,
        'notes' => [
            ['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => 15],
            ['eleve_id' => $eleve->id, 'matiere_id' => $autreMatiere->id, 'valeur' => 9],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 1]);
    $this->assertDatabaseHas('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id, 'valeur' => 15]);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id, 'valeur' => 9]);
});

test('a batch save is rejected once the examen saisie deadline has passed', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'anneeActive' => $anneeActive] = creerContexteEnseignant();
    $examenPasse = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(30)->toDateString(),
        'date_limite_saisie' => now()->subDays(20)->toDateString(),
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.batch-update', $classe), [
        'examen_id' => $examenPasse->id,
        'notes' => [
            ['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => 10],
        ],
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examenPasse->id]);
});

test('a batch save skips a student whose bulletin is already validé', function () {
    ['classe' => $classe, 'matiere' => $matiere, 'enseignant' => $enseignant, 'eleve' => $eleve, 'examen' => $examen] = creerContexteEnseignant();
    $inscription = Inscription::where('classe_id', $classe->id)->where('eleve_id', $eleve->id)->firstOrFail();
    Bulletin::factory()->create([
        'inscription_id' => $inscription->id,
        'examen_id' => $examen->id,
        'statut' => StatutBulletin::Valide,
    ]);

    $response = $this->actingAs($enseignant)->patchJson(route('enseignant.classes.notes.batch-update', $classe), [
        'examen_id' => $examen->id,
        'notes' => [
            ['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'valeur' => 17],
        ],
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'enregistrees' => 0]);
    $this->assertDatabaseMissing('notes', ['eleve_id' => $eleve->id, 'examen_id' => $examen->id]);
});

test('the notes table is disabled and shows a lock message once the saisie deadline has passed', function () {
    ['classe' => $classe, 'enseignant' => $enseignant, 'anneeActive' => $anneeActive] = creerContexteEnseignant();
    $examenPasse = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(30)->toDateString(),
        'date_limite_saisie' => now()->subDays(20)->toDateString(),
    ]);

    $response = $this->actingAs($enseignant)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examenPasse->id]));

    $response->assertOk();
    $response->assertSee('lecture seule');
    $response->assertSee('disabled', false);
});

test('a non-enseignant profile cannot access the espace enseignant', function () {
    $admin = User::factory()->administrateur()->create();

    $response = $this->actingAs($admin)->get(route('enseignant.classes.index'));

    $response->assertForbidden();
});

/**
 * A classe with 2 matières, each taught by a different enseignant — the
 * titulaire teaches Français only, a second enseignant teaches Mathématiques.
 */
function creerContexteTitulaireMultiMatiere(): array
{
    $anneeActive = AnneeAcademique::factory()->create([
        'est_active' => true,
        'date_debut' => now()->subMonths(3)->toDateString(),
        'date_fin' => now()->addMonths(6)->toDateString(),
    ]);
    $niveau = Niveau::factory()->create(['cycle' => CycleNiveau::Primaire]);
    $classe = Classe::factory()->create(['niveau_id' => $niveau->id, 'annee_academique_id' => $anneeActive->id]);

    $francais = Matiere::factory()->create(['nom' => 'Français']);
    $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
    ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $francais->id, 'coefficient' => 3]);
    ClasseMatiere::create(['classe_id' => $classe->id, 'matiere_id' => $maths->id, 'coefficient' => 4]);

    $titulaire = User::factory()->enseignant()->create();
    $profMaths = User::factory()->enseignant()->create();
    AffectationEnseignant::create(['enseignant_id' => $titulaire->id, 'classe_id' => $classe->id, 'matiere_id' => $francais->id, 'annee_academique_id' => $anneeActive->id, 'est_professeur_principal' => true]);
    AffectationEnseignant::create(['enseignant_id' => $profMaths->id, 'classe_id' => $classe->id, 'matiere_id' => $maths->id, 'annee_academique_id' => $anneeActive->id, 'est_professeur_principal' => false]);

    $eleve = Eleve::factory()->create();
    Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $classe->id]);

    $examen = Examen::factory()->create([
        'annee_academique_id' => $anneeActive->id,
        'systeme' => SystemeScolaire::Primaire,
        'date_examen' => now()->subDays(10)->toDateString(),
        'date_limite_saisie' => now()->addDays(5)->toDateString(),
    ]);

    return compact('anneeActive', 'niveau', 'classe', 'francais', 'maths', 'titulaire', 'profMaths', 'eleve', 'examen');
}

test('the titulaire sees every matière of the classe, including ones they do not teach', function () {
    ['classe' => $classe, 'maths' => $maths, 'profMaths' => $profMaths, 'eleve' => $eleve, 'examen' => $examen, 'titulaire' => $titulaire] = creerContexteTitulaireMultiMatiere();
    $classeMatiereMaths = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $maths->id)->first();
    Note::create([
        'eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiereMaths->id, 'examen_id' => $examen->id,
        'enseignant_id' => $profMaths->id, 'valeur' => 17, 'type' => TypeEvaluation::EvaluationMensuelle, 'numero' => 1, 'date_saisie' => now()->toDateString(),
    ]);

    $response = $this->actingAs($titulaire)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examen->id]));

    $response->assertOk();
    $response->assertSee('Mathématiques');
    $response->assertSee('value="17"', false);
});

test('the titulaire’s note input for a matière they do not teach is disabled', function () {
    ['classe' => $classe, 'maths' => $maths, 'francais' => $francais, 'examen' => $examen, 'titulaire' => $titulaire] = creerContexteTitulaireMultiMatiere();

    $response = $this->actingAs($titulaire)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examen->id]));
    $html = $response->getContent();

    expect(preg_match('/data-matiere-id="'.$maths->id.'" data-editable="0"/', $html))->toBe(1);
    expect(preg_match('/data-matiere-id="'.$francais->id.'" data-editable="1"/', $html))->toBe(1);
});

test('the titulaire cannot actually modify a note for a matière they do not teach', function () {
    ['classe' => $classe, 'maths' => $maths, 'eleve' => $eleve, 'examen' => $examen, 'titulaire' => $titulaire] = creerContexteTitulaireMultiMatiere();
    $classeMatiereMaths = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $maths->id)->first();

    $response = $this->actingAs($titulaire)->patchJson(route('enseignant.classes.notes.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $maths->id,
        'examen_id' => $examen->id,
        'valeur' => 2,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('notes', ['classe_matiere_id' => $classeMatiereMaths->id, 'valeur' => 2]);
});

test('the titulaire cannot comment on a matière they do not teach', function () {
    ['classe' => $classe, 'maths' => $maths, 'eleve' => $eleve, 'examen' => $examen, 'titulaire' => $titulaire] = creerContexteTitulaireMultiMatiere();

    $response = $this->actingAs($titulaire)->patchJson(route('enseignant.classes.commentaires-matiere.update', $classe), [
        'eleve_id' => $eleve->id,
        'matiere_id' => $maths->id,
        'examen_id' => $examen->id,
        'commentaire' => 'Tentative illégitime',
    ]);

    $response->assertForbidden();
});

test('a non-titulaire teacher still only sees their own assigned matière', function () {
    ['classe' => $classe, 'francais' => $francais, 'examen' => $examen, 'profMaths' => $profMaths] = creerContexteTitulaireMultiMatiere();

    $response = $this->actingAs($profMaths)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examen->id]));

    $response->assertOk();
    $response->assertDontSee($francais->nom);
});

test('a subject-only teacher never sees the "Moyenne" column', function () {
    ['classe' => $classe, 'examen' => $examen, 'profMaths' => $profMaths] = creerContexteTitulaireMultiMatiere();

    $response = $this->actingAs($profMaths)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examen->id]));

    $response->assertOk();
    $response->assertDontSee('>Moyenne<', false);
});

test('the titulaire’s moyenne column shows "—" until every matière is noted, then the real average', function () {
    ['classe' => $classe, 'francais' => $francais, 'maths' => $maths, 'eleve' => $eleve, 'examen' => $examen, 'titulaire' => $titulaire, 'profMaths' => $profMaths] = creerContexteTitulaireMultiMatiere();
    $cmFrancais = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $francais->id)->first();
    $cmMaths = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $maths->id)->first();

    Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmFrancais->id, 'examen_id' => $examen->id, 'enseignant_id' => $titulaire->id, 'valeur' => 15, 'type' => TypeEvaluation::EvaluationMensuelle, 'numero' => 1, 'date_saisie' => now()->toDateString()]);

    $response = $this->actingAs($titulaire)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examen->id]));
    $response->assertOk();
    $response->assertDontSee('15.00');

    Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmMaths->id, 'examen_id' => $examen->id, 'enseignant_id' => $profMaths->id, 'valeur' => 9, 'type' => TypeEvaluation::EvaluationMensuelle, 'numero' => 1, 'date_saisie' => now()->toDateString()]);

    $response = $this->actingAs($titulaire)->get(route('enseignant.classes.show', ['classe' => $classe, 'examen_id' => $examen->id]));
    $response->assertOk();
    $response->assertSee('12.00');
});

test('the titulaire cannot validate a bulletin until every matière has been noted', function () {
    ['classe' => $classe, 'francais' => $francais, 'eleve' => $eleve, 'examen' => $examen, 'titulaire' => $titulaire] = creerContexteTitulaireMultiMatiere();
    $cmFrancais = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $francais->id)->first();
    Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmFrancais->id, 'examen_id' => $examen->id, 'enseignant_id' => $titulaire->id, 'valeur' => 15, 'type' => TypeEvaluation::EvaluationMensuelle, 'numero' => 1, 'date_saisie' => now()->toDateString()]);

    $this->actingAs($titulaire)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Bon travail.',
    ]);

    $response = $this->actingAs($titulaire)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'statut' => StatutBulletin::Brouillon->value]);
});

test('the titulaire can validate a bulletin once every matière has been noted', function () {
    ['classe' => $classe, 'francais' => $francais, 'maths' => $maths, 'eleve' => $eleve, 'examen' => $examen, 'titulaire' => $titulaire, 'profMaths' => $profMaths] = creerContexteTitulaireMultiMatiere();
    $cmFrancais = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $francais->id)->first();
    $cmMaths = ClasseMatiere::where('classe_id', $classe->id)->where('matiere_id', $maths->id)->first();
    Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmFrancais->id, 'examen_id' => $examen->id, 'enseignant_id' => $titulaire->id, 'valeur' => 15, 'type' => TypeEvaluation::EvaluationMensuelle, 'numero' => 1, 'date_saisie' => now()->toDateString()]);
    Note::create(['eleve_id' => $eleve->id, 'classe_matiere_id' => $cmMaths->id, 'examen_id' => $examen->id, 'enseignant_id' => $profMaths->id, 'valeur' => 9, 'type' => TypeEvaluation::EvaluationMensuelle, 'numero' => 1, 'date_saisie' => now()->toDateString()]);

    $this->actingAs($titulaire)->patchJson(route('enseignant.classes.bulletins.update', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
        'resultat_global' => ResultatMensuel::Bien->value,
        'appreciation' => 'Bon travail.',
    ]);

    $response = $this->actingAs($titulaire)->patchJson(route('enseignant.classes.bulletins.valider', $classe), [
        'eleve_id' => $eleve->id,
        'examen_id' => $examen->id,
    ]);

    $response->assertOk()->assertJson(['ok' => true, 'statut' => 'valide']);
    $this->assertDatabaseHas('bulletins', ['examen_id' => $examen->id, 'statut' => StatutBulletin::Valide->value]);
});
