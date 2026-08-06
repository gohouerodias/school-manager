<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTuteurInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No `lien_parente` here, unlike UpdateTuteurRequest: this edits the
     * shared ParentTuteur record itself (see Tuteurs\TuteurController),
     * not one specific élève's relationship to them — lien_parente stays
     * per-élève, edited from the fiche's "Modifier le tuteur" panel.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom_prenom' => ['required', 'string', 'max:150'],
            'telephone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
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
