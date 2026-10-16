<?php

namespace App\Http\Requests;

use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A classe's nom isn't typed freely — it's "{niveau->libelle} {lettre}"
 * (see initClasseLettreFilter() in annee-academique-show.js, which only
 * lets the admin pick a `lettre` not already used by another classe of the
 * chosen niveau in this année, so this uniqueness check is mostly a
 * server-side safety net for a stale/tampered select).
 */
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
        return [
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'lettre' => ['required', 'string', 'regex:/^[A-Z]$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('niveau_id') || $validator->errors()->has('lettre')) {
                return;
            }

            $niveau = Niveau::find($this->input('niveau_id'));
            if (! $niveau) {
                return;
            }

            $anneeAcademiqueId = $this->route('anneeAcademique')?->id;
            $nom = "{$niveau->libelle} {$this->input('lettre')}";

            $existe = Classe::query()
                ->where('annee_academique_id', $anneeAcademiqueId)
                ->where('niveau_id', $niveau->id)
                ->where('nom', $nom)
                ->exists();

            if ($existe) {
                $validator->errors()->add('lettre', "La classe « {$nom} » existe déjà pour cette année académique. Choisissez une autre lettre (ex. « {$niveau->libelle} B »).");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'niveau_id.required' => 'Le niveau est obligatoire.',
            'lettre.required' => 'La lettre de la classe est obligatoire (ex : A).',
            'lettre.regex' => 'La lettre doit être une seule lettre majuscule, de A à Z.',
        ];
    }
}
