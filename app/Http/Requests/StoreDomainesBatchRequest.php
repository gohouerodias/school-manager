<?php

namespace App\Http\Requests;

use App\Enums\NiveauQualitatif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Équivalent maternelle de StoreNotesBatchRequest : toutes les cases
 * (valeur qualitative + observation optionnelle) modifiées ou ajoutées de
 * la grille depuis la dernière sauvegarde, envoyées en un seul appel — voir
 * Enseignant\EspaceEnseignantController::saveDomainesBatch().
 */
class StoreDomainesBatchRequest extends FormRequest
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
            'evaluations' => ['required', 'array', 'min:1'],
            'evaluations.*.eleve_id' => ['required', 'exists:eleves,id'],
            'evaluations.*.domaine_evaluation_id' => ['required', 'exists:domaines_evaluation,id'],
            'evaluations.*.valeur' => ['nullable', new Enum(NiveauQualitatif::class)],
            'evaluations.*.observation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evaluations.required' => 'Aucune modification à enregistrer.',
        ];
    }
}
