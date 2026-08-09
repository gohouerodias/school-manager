<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClasseRequest extends FormRequest
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
        $anneeAcademiqueId = $this->route('anneeAcademique')?->id;

        return [
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'nom' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classes', 'nom')->where(
                    fn ($query) => $query
                        ->where('annee_academique_id', $anneeAcademiqueId)
                        ->where('niveau_id', $this->input('niveau_id'))
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'niveau_id.required' => 'Le niveau est obligatoire.',
            'nom.required' => 'Le nom de la classe est obligatoire (ex : CM1 A).',
            'nom.unique' => 'Une classe de ce niveau porte déjà ce nom pour cette année académique.',
        ];
    }
}
