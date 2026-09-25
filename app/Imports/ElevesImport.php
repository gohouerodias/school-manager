<?php

namespace App\Imports;

use App\Enums\StatutEleve;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import du fichier "apprenants.xlsx" — mêmes colonnes que App\Exports\
 * ElevesExport (Matricule, Nom, Prénom, Sexe, Date de naissance, Classe,
 * Statut). Une ligne dont le Matricule correspond à un élève déjà existant
 * met à jour sa fiche ; sinon une nouvelle fiche est créée. La Classe (nom)
 * est recherchée dans l'année académique active uniquement — voir
 * Eleve::inscriptionActive() / EleveClasseController::update(), dont ce
 * traitement reprend exactement la même logique (mise à jour en place de
 * l'inscription active si elle existe déjà, sinon nouvelle inscription).
 *
 * Ne traite volontairement que ToCollection (pas ToModel) : chaque ligne est
 * gérée manuellement pour pouvoir rapporter un compte-rendu précis
 * (créés/mis à jour/erreurs) à l'écran d'import plutôt que d'échouer
 * silencieusement sur les lignes invalides.
 */
class ElevesImport implements ToCollection, WithHeadingRow
{
    public int $crees = 0;

    public int $misAJour = 0;

    /** @var array<int, string> */
    public array $erreurs = [];

    public function collection(Collection $rows): void
    {
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

        foreach ($rows as $index => $row) {
            $ligne = $index + 2; // +1 pour l'index base 0, +1 pour la ligne d'en-têtes.

            try {
                $this->traiterLigne($row, $anneeActive, $ligne);
            } catch (\Throwable $e) {
                $this->erreurs[] = "Ligne {$ligne} : erreur inattendue ({$e->getMessage()}).";
            }
        }
    }

    /**
     * @param  Collection<string, mixed>  $row
     */
    private function traiterLigne(Collection $row, ?AnneeAcademique $anneeActive, int $ligne): void
    {
        $matricule = $this->valeur($row, 'matricule');
        $nom = $this->valeur($row, 'nom');
        $prenom = $this->valeur($row, 'prenom');
        $sexe = $this->valeur($row, 'sexe');
        $dateNaissanceBrute = $this->valeur($row, 'date_de_naissance');
        $classeNom = $this->valeur($row, 'classe');
        $statutLibelle = $this->valeur($row, 'statut');

        if (! $nom && ! $prenom && ! $matricule) {
            return; // Ligne vide (fin de fichier, ligne de séparation…) : ignorée silencieusement.
        }

        $validator = Validator::make(
            ['nom' => $nom, 'prenom' => $prenom, 'sexe' => $sexe, 'date_naissance' => $dateNaissanceBrute],
            [
                'nom' => ['required', 'string', 'max:100'],
                'prenom' => ['required', 'string', 'max:100'],
                'sexe' => ['required', 'in:M,F'],
                'date_naissance' => ['required'],
            ],
            [
                'nom.required' => 'le nom est obligatoire',
                'prenom.required' => 'le prénom est obligatoire',
                'sexe.required' => 'le sexe est obligatoire (M ou F)',
                'sexe.in' => 'le sexe doit être "M" ou "F"',
                'date_naissance.required' => 'la date de naissance est obligatoire',
            ]
        );

        if ($validator->fails()) {
            $this->erreurs[] = "Ligne {$ligne} : ".implode(', ', $validator->errors()->all()).'.';

            return;
        }

        $dateNaissance = $this->parserDate($dateNaissanceBrute);
        if (! $dateNaissance) {
            $this->erreurs[] = "Ligne {$ligne} : date de naissance invalide (« {$dateNaissanceBrute} », attendu jj/mm/aaaa).";

            return;
        }

        $statut = match (mb_strtolower((string) $statutLibelle)) {
            'archivé', 'archive' => StatutEleve::Archive,
            'brouillon' => StatutEleve::Brouillon,
            default => StatutEleve::Actif,
        };

        $eleve = $matricule ? Eleve::query()->where('matricule', $matricule)->first() : null;
        $estMiseAJour = (bool) $eleve;

        if (! $eleve) {
            $eleve = new Eleve(['matricule' => $matricule]);
        }

        $eleve->fill([
            'nom' => $nom,
            'prenom' => $prenom,
            'sexe' => $sexe,
            'date_naissance' => $dateNaissance,
        ]);

        if ($statut === StatutEleve::Archive && $eleve->statut !== StatutEleve::Archive) {
            $eleve->date_archivage = now()->toDateString();
        } elseif ($statut !== StatutEleve::Archive) {
            $eleve->date_archivage = null;
        }
        $eleve->statut = $statut;

        $eleve->save();

        if ($classeNom) {
            $this->affecterClasse($eleve, $classeNom, $anneeActive, $ligne);
        }

        $estMiseAJour ? $this->misAJour++ : $this->crees++;
    }

    private function affecterClasse(Eleve $eleve, string $classeNom, ?AnneeAcademique $anneeActive, int $ligne): void
    {
        if (! $anneeActive) {
            $this->erreurs[] = "Ligne {$ligne} : aucune année académique active, la classe « {$classeNom} » n'a pas pu être affectée.";

            return;
        }

        $classe = Classe::query()
            ->where('nom', $classeNom)
            ->where('annee_academique_id', $anneeActive->id)
            ->first();

        if (! $classe) {
            $this->erreurs[] = "Ligne {$ligne} : classe « {$classeNom} » introuvable dans l'année académique active ({$anneeActive->libelle}).";

            return;
        }

        $eleve->load(['inscriptions.classe.anneeAcademique']);
        $inscriptionActive = $eleve->inscriptionActive();

        if ($inscriptionActive) {
            if ($inscriptionActive->classe_id !== $classe->id) {
                $inscriptionActive->update(['classe_id' => $classe->id]);
            }

            return;
        }

        Inscription::query()->create([
            'eleve_id' => $eleve->id,
            'classe_id' => $classe->id,
            'date_inscription' => now()->toDateString(),
        ]);
    }

    /**
     * @param  Collection<string, mixed>  $row
     */
    private function valeur(Collection $row, string $cle): ?string
    {
        $brut = $row->get($cle);
        $valeur = is_string($brut) ? trim($brut) : $brut;

        if ($valeur === null || $valeur === '' || $valeur === '—') {
            return null;
        }

        return (string) $valeur;
    }

    private function parserDate(?string $valeur): ?string
    {
        if (! $valeur) {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $valeur)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
