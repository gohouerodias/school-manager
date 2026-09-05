<?php

namespace App\Http\Requests;

use App\Enums\CycleNiveau;
use App\Enums\ProfilUtilisateur;
use App\Models\AffectationEnseignant;
use App\Models\Classe;
use App\Models\Matiere;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Two very different affectation models depending on the classe's cycle
 * (see Academique\AffectationEnseignantController):
 *  - Maternelle/Primaire: one teacher for the whole classe, every matière of
 *    its programme — no `matiere_ids` needed here.
 *  - Collège: one teacher per matière, but they can be affected to several
 *    matières of the same classe in a single submission — `matiere_ids`
 *    required (US A.3).
 */
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
        $classeEntiere = $this->classeEstEnModeEntiere();

        return [
            'enseignant_id' => [
                'required',
                Rule::exists('users', 'id')->where('profil', ProfilUtilisateur::Enseignant->value),
            ],
            'classe_id' => [
                'required',
                Rule::exists('classes', 'id')->where('annee_academique_id', $anneeAcademiqueId),
            ],
            'matiere_ids' => $classeEntiere ? ['nullable'] : ['required', 'array', 'min:1'],
            'matiere_ids.*' => ['integer', 'exists:matieres,id'],
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
            'matiere_ids.required' => 'Sélectionnez au moins une matière.',
            'matiere_ids.min' => 'Sélectionnez au moins une matière.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $classeId = $this->input('classe_id');
            $classe = $classeId ? Classe::find($classeId) : null;

            if (! $classe) {
                return;
            }

            if ($this->classeEstEnModeEntiere()) {
                if ($classe->matieres()->count() === 0) {
                    $validator->errors()->add('classe_id', "Cette classe n'a pas encore de programme (matières) configuré.");
                }

                // No per-matière/doublon checks here: store() replaces every
                // existing affectation for this classe/année wholesale, so a
                // "duplicate" of the previous teacher's rows is expected,
                // not an error.
                return;
            }

            $matiereIds = collect($this->input('matiere_ids', []))->filter()->map(fn ($id) => (int) $id)->values();
            $enseignantId = $this->input('enseignant_id');

            if ($matiereIds->isEmpty()) {
                return;
            }

            $programmeIds = $classe->matieres()->pluck('matieres.id');
            $horsProgramme = $matiereIds->diff($programmeIds);

            if ($horsProgramme->isNotEmpty()) {
                $noms = Matiere::query()->whereIn('id', $horsProgramme)->pluck('nom')->implode(', ');
                $validator->errors()->add('matiere_ids', "Les matières suivantes ne font pas partie du programme de « {$classe->nom} » : {$noms}.");
            }

            if ($enseignantId && $classeId) {
                $doublons = AffectationEnseignant::query()
                    ->where('enseignant_id', $enseignantId)
                    ->where('classe_id', $classeId)
                    ->where('annee_academique_id', $this->route('anneeAcademique')?->id)
                    ->whereIn('matiere_id', $matiereIds)
                    ->pluck('matiere_id');

                if ($doublons->isNotEmpty()) {
                    $noms = Matiere::query()->whereIn('id', $doublons)->pluck('nom')->implode(', ');
                    $validator->errors()->add('matiere_ids', "Cet enseignant est déjà affecté à cette classe pour : {$noms}.");
                }

                // Une matière d'une classe n'a qu'un seul enseignant à la
                // fois : deux enseignants ne peuvent pas se partager la même
                // matière dans la même classe. Retirez d'abord l'enseignant
                // en place (panneau « Gérer ») avant d'en affecter un autre.
                $dejaPrises = AffectationEnseignant::query()
                    ->where('classe_id', $classeId)
                    ->where('annee_academique_id', $this->route('anneeAcademique')?->id)
                    ->where('enseignant_id', '!=', $enseignantId)
                    ->whereIn('matiere_id', $matiereIds)
                    ->with(['enseignant', 'matiere'])
                    ->get();

                if ($dejaPrises->isNotEmpty()) {
                    $detail = $dejaPrises
                        ->map(fn ($affectation) => "{$affectation->matiere->nom} ({$affectation->enseignant->name})")
                        ->implode(', ');
                    $validator->errors()->add('matiere_ids', "Ces matières ont déjà un autre enseignant pour cette classe : {$detail}. Retirez-le d'abord avant d'en affecter un nouveau.");
                }
            }
        });
    }

    /**
     * Maternelle/Primaire: one teacher for the whole classe (all matières at
     * once) — no matière to pick. Collège: one teacher per matière, unchanged.
     */
    private function classeEstEnModeEntiere(): bool
    {
        $classe = Classe::with('niveau')->find($this->input('classe_id'));

        return $classe && in_array($classe->niveau->cycle, [CycleNiveau::Maternelle, CycleNiveau::Primaire], true);
    }
}
