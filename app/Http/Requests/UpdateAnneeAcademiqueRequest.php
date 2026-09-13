<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Le libellé reste fixe une fois l'année créée (voir Academique\
 * AnneeAcademiqueController::update()) — seules les dates et le nombre
 * d'évaluations prévues se modifient. Les nouvelles dates doivent continuer
 * à englober tous les examens déjà créés pour cette année (voir
 * StoreExamenRequest/UpdateExamenRequest, qui exigent l'inverse : que les
 * dates d'un examen restent dans la fenêtre de son année).
 */
class UpdateAnneeAcademiqueRequest extends FormRequest
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
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            // Voir StoreAnneeAcademiqueRequest : "nullable" pour rester
            // compatible avec tout appel existant qui ne l'envoie pas encore.
            'nombre_evaluations_prevues' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
            'nombre_evaluations_prevues.integer' => "Le nombre d'évaluations prévues doit être un nombre entier.",
            'nombre_evaluations_prevues.min' => "Le nombre d'évaluations prévues doit être d'au moins 1.",
            'nombre_evaluations_prevues.max' => "Le nombre d'évaluations prévues ne peut pas dépasser 20.",
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $anneeAcademique = $this->route('anneeAcademique');
            $dateDebut = $this->input('date_debut');
            $dateFin = $this->input('date_fin');

            if (! $anneeAcademique || ! $dateDebut || ! $dateFin) {
                return;
            }

            $examenHorsFenetre = $anneeAcademique->examens()
                ->get()
                ->first(function ($examen) use ($dateDebut, $dateFin) {
                    foreach (['date_examen', 'date_limite_saisie'] as $champ) {
                        $valeur = $examen->{$champ}?->format('Y-m-d');

                        if ($valeur && ($valeur < $dateDebut || $valeur > $dateFin)) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($examenHorsFenetre) {
                $validator->errors()->add('date_fin', "Impossible : l'examen du {$examenHorsFenetre->date_examen->format('d/m/Y')} (et/ou sa date limite de saisie) sort de cette nouvelle période. Modifiez ou supprimez d'abord cet examen.");
            }
        });
    }
}
