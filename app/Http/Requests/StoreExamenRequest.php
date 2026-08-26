<?php

namespace App\Http\Requests;

use App\Enums\SystemeScolaire;
use App\Models\AnneeAcademique;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamenRequest extends FormRequest
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
            'systeme' => ['required', 'in:maternelle,primaire,secondaire'],
            'date_examen' => ['required_if:systeme,maternelle,primaire', 'date'],
            'date_limite_saisie' => ['required_if:systeme,maternelle,primaire', 'date', 'after_or_equal:date_examen'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'systeme.required' => 'Choisissez le système scolaire concerné par cet examen.',
            'systeme.in' => 'Système scolaire invalide.',
            'date_examen.required_if' => "La date de l'examen est obligatoire.",
            'date_limite_saisie.required_if' => 'La date limite de saisie des notes est obligatoire.',
            'date_limite_saisie.after_or_equal' => "La date limite de saisie doit être égale ou postérieure à la date de l'examen.",
        ];
    }

    /**
     * Only the systèmes disponibles (voir SystemeScolaire::estDisponible())
     * actually create un Examen (see Academique\ExamenController::store()),
     * scoped to the currently active année académique — so their dates only
     * make sense validated against that année's own window.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $systeme = SystemeScolaire::tryFrom($this->input('systeme'));

            if (! $systeme || ! $systeme->estDisponible()) {
                return;
            }

            $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

            if (! $anneeActive) {
                $validator->errors()->add('systeme', 'Aucune année académique active — démarrez une année avant de créer un examen.');

                return;
            }

            foreach (['date_examen', 'date_limite_saisie'] as $field) {
                $value = $this->input($field);

                if (! $value) {
                    continue;
                }

                if ($value < $anneeActive->date_debut->format('Y-m-d') || $value > $anneeActive->date_fin->format('Y-m-d')) {
                    $validator->errors()->add($field, "Cette date doit être comprise dans la période de l'année académique active ({$anneeActive->date_debut->format('d/m/Y')} – {$anneeActive->date_fin->format('d/m/Y')}).");
                }
            }
        });
    }
}
