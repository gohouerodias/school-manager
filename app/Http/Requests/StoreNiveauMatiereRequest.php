<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNiveauMatiereRequest extends FormRequest
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
            'matiere_id' => [
                'required',
                'exists:matieres,id',
                Rule::unique('niveau_matiere', 'matiere_id')->where(
                    fn ($query) => $query
                        ->where('niveau_id', $this->input('niveau_id'))
                        ->where('annee_academique_id', $anneeAcademiqueId)
                ),
            ],
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'niveau_id.required' => 'Le niveau est obligatoire.',
            'matiere_id.required' => 'La matière est obligatoire.',
            'matiere_id.unique' => 'Cette matière est déjà au programme de ce niveau pour cette année.',
            'coefficient.required' => 'Le coefficient est obligatoire.',
        ];
    }
}
