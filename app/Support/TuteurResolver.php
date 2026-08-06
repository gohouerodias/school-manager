<?php

namespace App\Support;

use App\Models\ParentTuteur;

/**
 * Resolves the ParentTuteur a "nom et prénom" (+ téléphone) input refers to,
 * reused by every place that adds or edits a tuteur — Eleves\TuteurController
 * (the fiche modal's "Ajouter un tuteur" / "Modifier le tuteur" panels) and
 * EleveWizardController (étape 3's "Ajouter un parent / tuteur") — instead
 * of each reimplementing its own dedup logic:
 *
 * - `existing_id` present : the agent confirmed a match proposed by the
 *   "does this parent already exist" quick-search (see resources/js/
 *   tuteur-quick-search.js) — that record is reused directly.
 * - otherwise : a ParentTuteur already on file with the same nom + prénom
 *   (+ téléphone, if given) is reused; failing that, a new one is created.
 */
class TuteurResolver
{
    /**
     * @param  array{existing_id?: int|string|null, nom_prenom?: string|null, telephone?: string|null, email?: string|null}  $donnees
     */
    public static function resolveOrCreate(array $donnees): ?ParentTuteur
    {
        if (! empty($donnees['existing_id'])) {
            return ParentTuteur::find($donnees['existing_id']);
        }

        $nomPrenom = trim((string) ($donnees['nom_prenom'] ?? ''));

        if ($nomPrenom === '') {
            return null;
        }

        [$nom, $prenom] = self::splitNomPrenom($nomPrenom);
        $telephone = $donnees['telephone'] ?? null;

        $tuteur = ParentTuteur::query()
            ->where('nom', $nom)
            ->where('prenom', $prenom)
            ->when($telephone, fn ($query) => $query->where('telephone', $telephone))
            ->first();

        return $tuteur ?? ParentTuteur::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => $telephone,
            'email' => $donnees['email'] ?? null,
        ]);
    }

    /**
     * Splits a single "Nom et prénom" input into its two columns — last word
     * = nom, everything before = prénom — same convention used across the
     * app (account-edit, TuteurController) since parent_tuteurs keeps them
     * separate.
     *
     * @return array{0: string, 1: string} [nom, prénom]
     */
    public static function splitNomPrenom(string $nomPrenom): array
    {
        $mots = preg_split('/\s+/', trim($nomPrenom)) ?: [];
        $nom = array_pop($mots) ?? $nomPrenom;
        $prenom = implode(' ', $mots);

        return [$nom, $prenom];
    }
}
