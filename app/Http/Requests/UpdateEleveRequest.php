<?php

namespace App\Http\Requests;

use App\Models\ChampPersonnalise;
use App\Models\Eleve;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEleveRequest extends FormRequest
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
     * Same duplicate warning as StoreEleveRequest, excluding the élève
     * currently being edited (it will always match itself otherwise).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nom = $this->input('nom');
            $prenom = $this->input('prenom');
            $dateNaissance = $this->input('date_naissance');
            $current = $this->route('eleve');

            if (! $nom || ! $prenom || ! $dateNaissance) {
                return;
            }

            $existant = Eleve::query()
                ->where('nom', $nom)
                ->where('prenom', $prenom)
                ->whereDate('date_naissance', $dateNaissance)
                ->when($current, fn ($query) => $query->whereKeyNot($current->id))
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
