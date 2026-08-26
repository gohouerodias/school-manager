<?php

namespace App\Http\Requests;

use App\Enums\CycleNiveau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `ordre` isn't editable here either — see StoreNiveauRequest's note.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:50', Rule::unique('niveaux', 'libelle')->ignore($this->route('niveau'))],
            'cycle' => ['required', Rule::enum(CycleNiveau::class)],
            'premiere_scolarisation' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.required' => 'Le libellé du niveau est obligatoire.',
            'libelle.unique' => 'Ce niveau existe déjà.',
            'cycle.required' => 'Le cycle est obligatoire.',
        ];
    }
}
