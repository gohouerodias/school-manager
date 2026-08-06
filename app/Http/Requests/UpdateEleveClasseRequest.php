<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEleveClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `classe_id` is nullable — an empty value means "Sans classe" (removes
     * the élève's current-année assignment). Whether a non-null id actually
     * belongs to the currently active année académique is re-checked in
     * EleveClasseController::update(), since that's a business rule rather
     * than a simple existence check.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'classe_id' => ['nullable', Rule::exists('classes', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.exists' => "La classe sélectionnée n'existe pas.",
        ];
    }
}
