<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnneeAcademiqueRequest extends FormRequest
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
            'libelle' => ['required', 'string', 'max:20', Rule::unique('annees_academiques', 'libelle')],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            // "nullable" et non "required" : ce champ a une valeur par
            // défaut de 1 en base (voir sa migration) — le rendre obligatoire
            // casserait tout appel existant à cette route qui ne le fournit
            // pas (tests compris). Le formulaire (voir
            // academique/annees/index.blade.php) l'envoie toujours en pratique.
            'nombre_evaluations_prevues' => ['nullable', 'integer', 'min:1', 'max:20'],
            // Non pris en compte immédiatement : lu au démarrage de cette
            // année (voir AnneeAcademiqueController::demarrer()), pas à sa
            // création, puisque la promotion nécessite que les classes de
            // cette nouvelle année existent déjà.
            'promouvoir_automatiquement' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.required' => "Le libellé de l'année académique est obligatoire (ex : 2026-2027).",
            'libelle.unique' => 'Cette année académique existe déjà.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
            'nombre_evaluations_prevues.integer' => "Le nombre d'évaluations prévues doit être un nombre entier.",
            'nombre_evaluations_prevues.min' => "Le nombre d'évaluations prévues doit être d'au moins 1.",
            'nombre_evaluations_prevues.max' => "Le nombre d'évaluations prévues ne peut pas dépasser 20.",
        ];
    }
}
