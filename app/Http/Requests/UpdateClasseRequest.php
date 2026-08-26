<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the classe's lettre can be changed — its niveau stays fixed (see
 * ClasseController::update()'s doc comment for why), so uniqueness only
 * needs to be re-checked against the classe's own (unchanged) niveau/année.
 */
class UpdateClasseRequest extends FormRequest
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
            'lettre' => ['required', 'string', 'regex:/^[A-Z]$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('lettre')) {
                return;
            }

            /** @var Classe $classe */
            $classe = $this->route('classe');
            $classe->loadMissing('niveau');
            $nom = "{$classe->niveau->libelle} {$this->input('lettre')}";

            $existe = Classe::query()
                ->where('annee_academique_id', $classe->annee_academique_id)
                ->where('niveau_id', $classe->niveau_id)
                ->where('nom', $nom)
                ->where('id', '!=', $classe->id)
                ->exists();

            if ($existe) {
                $validator->errors()->add('lettre', "La classe « {$nom} » existe déjà pour cette année.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lettre.required' => 'La lettre de la classe est obligatoire (ex : A).',
            'lettre.regex' => 'La lettre doit être une seule lettre majuscule, de A à Z.',
        ];
    }
}
