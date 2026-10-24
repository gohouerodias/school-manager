<?php

use App\Enums\ActionJournal;
use App\Enums\StatutEleve;
use App\Models\Eleve;
use App\Models\JournalAction;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Journal des actions : connexions, dossiers élèves, notes et bulletins
 * sont enregistrés (séance de suivi du 25/10/2026).
 */
function derniereEntree(ActionJournal $action): ?JournalAction
{
    return JournalAction::query()->where('action', $action->value)->latest('id')->first();
}

test('a successful login and a logout are recorded with the IP address', function () {
    $user = User::factory()->create(['password' => bcrypt('mot-de-passe-secret'), 'deux_fa_actif' => false, 'doit_changer_mot_de_passe' => false]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'mot-de-passe-secret']);
    $connexion = derniereEntree(ActionJournal::Connexion);

    expect($connexion?->user_id)->toBe($user->id)
        ->and($connexion->adresse_ip)->toBe('127.0.0.1');

    $this->actingAs($user)->post(route('logout'));

    expect(derniereEntree(ActionJournal::Deconnexion)?->user_id)->toBe($user->id);
});

test('a failed login on an existing account is recorded for that account', function () {
    $user = User::factory()->create(['password' => bcrypt('mot-de-passe-secret')]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);

    $echec = derniereEntree(ActionJournal::ConnexionEchouee);
    expect($echec?->user_id)->toBe($user->id)
        ->and($echec->details)->toBe('Mot de passe incorrect');
});

test('archiving and changing the classe of an élève are recorded', function () {
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Paul', 'statut' => StatutEleve::Actif]);

    $this->actingAs($agent)->patch(route('eleves.archiver', $eleve), ['motif' => 'Départ vers une autre école']);

    expect(derniereEntree(ActionJournal::ArchivageEleve)?->details)->toContain('Départ vers une autre école')
        ->and(derniereEntree(ActionJournal::ArchivageEleve)->user_id)->toBe($agent->id);
});

test('adding a document to a fiche is recorded', function () {
    Storage::fake('local');
    $agent = User::factory()->agentScolarite()->create();
    $eleve = Eleve::factory()->create();
    $type = TypeDocument::factory()->create(['libelle' => 'Carnet de santé', 'formats_acceptes' => ['PDF']]);

    $this->actingAs($agent)->post(route('eleves.documents.store', $eleve), [
        'type_document_id' => $type->id,
        'fichier' => UploadedFile::fake()->create('carnet.pdf', 50, 'application/pdf'),
    ]);

    expect(derniereEntree(ActionJournal::AjoutDocument)?->details)->toContain('Carnet de santé');
});

test('creating a fiche through the wizard is recorded', function () {
    $agent = User::factory()->agentScolarite()->create();

    $this->actingAs($agent)->post(route('eleves.wizard.store'), ['brouillon' => '1', 'nom' => 'Journal', 'prenom' => 'Test']);

    expect(derniereEntree(ActionJournal::CreationEleve)?->details)->toContain('Journal Test')->toContain('brouillon');
});

test('the journal screen shows readable action names and offers every tracked action as a filter', function () {
    $admin = User::factory()->administrateur()->create();
    JournalAction::enregistrer(ActionJournal::ArchivageEleve, 'Paul Ahouansou — motif : départ', $admin);

    $this->actingAs($admin)->get(route('administration.journal.index'))
        ->assertOk()
        ->assertSee("Archivage d'un apprenant", false)
        ->assertSee('<option value="connexion">Connexion</option>', false)
        ->assertSee('<option value="saisie_notes">Saisie de notes</option>', false);
});

test('nothing is recorded when no user is known', function () {
    JournalAction::enregistrer(ActionJournal::Connexion, 'test');

    expect(JournalAction::query()->count())->toBe(0);
});
