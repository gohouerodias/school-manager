<?php

namespace App\Http\Requests;

use App\Models\NiveauMatiere;
use Illuminate\Foundation\Http\FormRequest;

class StoreNiveauMatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accepts several matières at once (see initNiveauMatierePendingList()
     * in annee-academique-show.js), so the admin doesn't have to reopen this
     * panel once per matière to build a whole niveau's programme.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'matieres' => ['required', 'array', 'min:1'],
            'matieres.*.matiere_id' => ['required', 'exists:matieres,id'],
            'matieres.*.coefficient' => ['required', 'numeric', 'min:0.5', 'max:20'],
        ];
    }

    /**
     * Duplicate-detection can't be expressed with Rule::unique on a
     * wildcard array, so it's done by hand: within the submitted list
     * itself, and against what's already in this niveau/année's programme.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lignes = collect($this->input('matieres', []))
                ->filter(fn ($ligne) => filled($ligne['matiere_id'] ?? null));

            $matiereIds = $lignes->pluck('matiere_id');

            if ($matiereIds->duplicates()->isNotEmpty()) {
                $validator->errors()->add('matieres', 'Une même matière ne peut pas être ajoutée deux fois à la fois.');
            }

            $anneeAcademiqueId = $this->route('anneeAcademique')?->id;
            $niveauId = $this->input('niveau_id');

            $dejaPresentes = NiveauMatiere::query()
                ->where('niveau_id', $niveauId)
                ->where('annee_academique_id', $anneeAcademiqueId)
                ->whereIn('matiere_id', $matiereIds)
                ->with('matiere')
                ->get();

            if ($dejaPresentes->isNotEmpty()) {
                $noms = $dejaPresentes->pluck('matiere.nom')->implode(', ');
                $validator->errors()->add('matieres', "Déjà au programme de ce niveau : {$noms}.");
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
            'matieres.required' => 'Ajoutez au moins une matière à la liste.',
            'matieres.*.matiere_id.required' => 'La matière est obligatoire.',
            'matieres.*.coefficient.required' => 'Le coefficient est obligatoire.',
        ];
    }
}
