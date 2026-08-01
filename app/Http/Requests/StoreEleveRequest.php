<?php

namespace App\Http\Requests;

use App\Models\ChampPersonnalise;
use App\Models\Eleve;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEleveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access to this feature is already gated by route middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'sexe' => ['required', Rule::in(['M', 'F'])],
            'date_naissance' => ['required', 'date', 'before:today'],
            'niveau_souhaite_id' => ['nullable', 'exists:niveaux,id'],
            'champs' => ['array'],
        ];

        foreach (ChampPersonnalise::query()->get() as $champ) {
            $rules["champs.{$champ->id}"] = [$champ->obligatoire ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'sexe.required' => 'Le sexe est obligatoire.',
            'date_naissance.required' => 'La date de naissance est obligatoire.',
        ];
    }

    /**
     * Warn about likely duplicates: same nom + prénom + date de naissance
     * already on file. Referencing the existing matricule lets the agent
     * scolarité check the existing dossier before deciding whether this is
     * genuinely a new élève.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nom = $this->input('nom');
            $prenom = $this->input('prenom');
            $dateNaissance = $this->input('date_naissance');

            if (! $nom || ! $prenom || ! $dateNaissance) {
                return;
            }

            $existant = Eleve::query()
                ->where('nom', $nom)
                ->where('prenom', $prenom)
                ->whereDate('date_naissance', $dateNaissance)
                ->first();

            if ($existant) {
                $validator->errors()->add(
                    'nom',
                    "Un élève portant ce nom, ce prénom et cette date de naissance existe déjà (matricule {$existant->matricule}). Vérifiez qu'il ne s'agit pas d'un doublon avant de continuer."
                );
            }
        });
    }
}
