<?php

namespace App\Http\Requests;

use App\Enums\ProfilUtilisateur;
use App\Models\AffectationEnseignant;
use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAffectationEnseignantRequest extends FormRequest
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
            'enseignant_id' => [
                'required',
                Rule::exists('users', 'id')->where('profil', ProfilUtilisateur::Enseignant->value),
            ],
            'classe_id' => [
                'required',
                Rule::exists('classes', 'id')->where('annee_academique_id', $anneeAcademiqueId),
            ],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'est_professeur_principal' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enseignant_id.required' => "L'enseignant est obligatoire.",
            'enseignant_id.exists' => "Cet utilisateur n'est pas enregistré comme enseignant.",
            'classe_id.required' => 'La classe est obligatoire.',
            'classe_id.exists' => "Cette classe n'appartient pas à cette année académique.",
            'matiere_id.required' => 'La matière est obligatoire.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $classeId = $this->input('classe_id');
            $matiereId = $this->input('matiere_id');
            $enseignantId = $this->input('enseignant_id');
            $anneeAcademiqueId = $this->route('anneeAcademique')?->id;

            if (! $classeId || ! $matiereId) {
                return;
            }

            $classe = Classe::find($classeId);

            if ($classe && ! $classe->matieres()->where('matieres.id', $matiereId)->exists()) {
                $validator->errors()->add('matiere_id', "Cette matière ne fait pas partie du programme de « {$classe->nom} ».");
            }

            if ($enseignantId && $classeId && $matiereId) {
                $doublon = AffectationEnseignant::query()
                    ->where('enseignant_id', $enseignantId)
                    ->where('classe_id', $classeId)
                    ->where('matiere_id', $matiereId)
                    ->where('annee_academique_id', $anneeAcademiqueId)
                    ->exists();

                if ($doublon) {
                    $validator->errors()->add('matiere_id', 'Cet enseignant est déjà affecté à cette classe pour cette matière.');
                }
            }
        });
    }
}
