<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Désigner un titulaire ne peut se faire que parmi les enseignants déjà
 * affectés à la classe (US A.4) — voir Academique\
 * AffectationEnseignantController::designerTitulaire().
 */
class DesignerTitulaireRequest extends FormRequest
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
        $classeId = $this->route('classe')?->id;

        return [
            'enseignant_id' => [
                'required',
                Rule::exists('affectations_enseignant', 'enseignant_id')->where('classe_id', $classeId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enseignant_id.required' => "L'enseignant est obligatoire.",
            'enseignant_id.exists' => "Cet enseignant n'est pas affecté à cette classe.",
        ];
    }
}
