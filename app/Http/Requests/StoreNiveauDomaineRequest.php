<?php

namespace App\Http\Requests;

use App\Models\NiveauDomaine;
use Illuminate\Foundation\Http\FormRequest;

class StoreNiveauDomaineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accepte plusieurs domaines à la fois — même principe que
     * StoreNiveauMatiereRequest, sans coefficient (la maternelle n'a pas de
     * moyenne chiffrée).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'domaines' => ['required', 'array', 'min:1'],
            'domaines.*' => ['required', 'exists:domaines_evaluation,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $domaineIds = collect($this->input('domaines', []))->filter(fn ($id) => filled($id));

            if ($domaineIds->duplicates()->isNotEmpty()) {
                $validator->errors()->add('domaines', 'Un même domaine ne peut pas être ajouté deux fois à la fois.');
            }

            $anneeAcademiqueId = $this->route('anneeAcademique')?->id;
            $niveauId = $this->input('niveau_id');

            $dejaPresents = NiveauDomaine::query()
                ->where('niveau_id', $niveauId)
                ->where('annee_academique_id', $anneeAcademiqueId)
                ->whereIn('domaine_evaluation_id', $domaineIds)
                ->with('domaineEvaluation')
                ->get();

            if ($dejaPresents->isNotEmpty()) {
                $noms = $dejaPresents->pluck('domaineEvaluation.nom')->implode(', ');
                $validator->errors()->add('domaines', "Déjà au programme de ce niveau : {$noms}.");
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
            'domaines.required' => 'Ajoutez au moins un domaine à la liste.',
        ];
    }
}
