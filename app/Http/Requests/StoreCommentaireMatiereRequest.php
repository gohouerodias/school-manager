<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentaireMatiereRequest extends FormRequest
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
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
