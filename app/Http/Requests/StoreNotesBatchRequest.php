<?php

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Toutes les cases modifiées ou ajoutées de la feuille de saisie depuis la
 * dernière sauvegarde, envoyées en un seul appel par le bouton « Enregistrer
 * les modifications » (voir resources/js/enseignant.js et
 * Enseignant\EspaceEnseignantController::saveNotesBatch()). Ownership
 * (matière affectée à cet enseignant) et le délai de saisie sont vérifiés
 * dans le contrôleur, pas ici — voir StoreNoteRequest pour la même logique
 * côté sauvegarde unitaire.
 */
class StoreNotesBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'examen_id' => ['required', 'exists:examens,id'],
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.eleve_id' => ['required', 'exists:eleves,id'],
            'notes.*.matiere_id' => ['required', 'exists:matieres,id'],
            'notes.*.valeur' => ['nullable', 'numeric', 'min:0', 'max:20'],
            // Primaire : la note est la somme des deux critères (voir Note::totalDesCriteres()).
            'notes.*.critere_minimal' => ['nullable', 'numeric', 'min:0', 'max:'.Note::CRITERE_MINIMAL_MAX],
            'notes.*.critere_perfectionnement' => ['nullable', 'numeric', 'min:0', 'max:'.Note::CRITERE_PERFECTIONNEMENT_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Aucune modification à enregistrer.',
            'notes.*.valeur.numeric' => 'Une note doit être un nombre.',
            'notes.*.valeur.min' => 'Une note ne peut pas être négative.',
            'notes.*.valeur.max' => 'Une note ne peut pas dépasser 20.',
        ];
    }
}
