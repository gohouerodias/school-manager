<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One grade cell of the espace enseignant's "Saisie des notes" grid.
 * Ownership (this teacher is actually affected to this classe+matière) and
 * the examen's saisie deadline are enforced in the controller, not here —
 * both need the route-bound Classe, which a FormRequest can read but the
 * error messages read better coming from the controller (see
 * Enseignant\EspaceEnseignantController::saveNote()).
 */
class StoreNoteRequest extends FormRequest
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
            'eleve_id' => ['required', 'exists:eleves,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'examen_id' => ['required', 'exists:examens,id'],
            'valeur' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valeur.numeric' => 'La note doit être un nombre.',
            'valeur.min' => 'La note ne peut pas être négative.',
            'valeur.max' => 'La note ne peut pas dépasser 20.',
        ];
    }
}
