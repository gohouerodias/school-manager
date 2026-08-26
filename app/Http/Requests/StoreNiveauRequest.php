<?php

namespace App\Http\Requests;

use App\Enums\CycleNiveau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `ordre` isn't submitted — a new niveau is always appended last (see
     * NiveauController::store()); reordering afterwards is done via the ↑/↓
     * buttons (NiveauController::monter()/descendre()), not by typing a
     * number.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:50', Rule::unique('niveaux', 'libelle')],
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
