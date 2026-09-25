<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParametreAcademiqueRequest extends FormRequest
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
            'seuil_passage' => ['required', 'numeric', 'min:0', 'max:20'],
            'duree_conservation_donnees' => ['required', 'integer', 'min:0', 'max:600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seuil_passage.required' => 'Le seuil de passage est obligatoire.',
            'seuil_passage.numeric' => 'Le seuil de passage doit être un nombre.',
            'seuil_passage.min' => 'Le seuil de passage ne peut pas être négatif.',
            'seuil_passage.max' => 'Le seuil de passage ne peut pas dépasser 20.',
            'duree_conservation_donnees.required' => 'La durée de conservation des données est obligatoire.',
            'duree_conservation_donnees.integer' => 'La durée de conservation doit être un nombre entier de mois.',
            'duree_conservation_donnees.min' => 'La durée de conservation ne peut pas être négative.',
            'duree_conservation_donnees.max' => 'La durée de conservation ne peut pas dépasser 600 mois.',
        ];
    }
}
