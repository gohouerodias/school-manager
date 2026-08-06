<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTuteurRequest extends FormRequest
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
            'nom_prenom' => ['required', 'string', 'max:150'],
            'lien_parente' => ['required', Rule::in(['Père', 'Mère', 'Tuteur légal', 'Autre'])],
            'telephone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            // Set by the "does this parent already exist" quick-search (see
            // resources/js/tuteur-quick-search.js) once the agent confirms a
            // suggested match — lets App\Support\TuteurResolver associate
            // that exact record instead of re-guessing from nom/prénom/téléphone.
            'existing_id' => ['nullable', 'exists:parent_tuteurs,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom_prenom.required' => 'Le nom et prénom du tuteur sont obligatoires.',
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
        ];
    }
}
