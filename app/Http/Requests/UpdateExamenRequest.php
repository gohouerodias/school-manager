<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the dates are editable — `systeme` stays fixed once an examen
 * exists (changing it after notes may already be attached would blur what
 * those notes actually mean; see Academique\ExamenController::update()).
 */
class UpdateExamenRequest extends FormRequest
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
            'date_examen' => ['required', 'date'],
            'date_limite_saisie' => ['required', 'date', 'after_or_equal:date_examen'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_examen.required' => "La date de l'examen est obligatoire.",
            'date_limite_saisie.required' => 'La date limite de saisie des notes est obligatoire.',
            'date_limite_saisie.after_or_equal' => "La date limite de saisie doit être égale ou postérieure à la date de l'examen.",
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $examen = $this->route('examen');
            $anneeAcademique = $examen?->anneeAcademique;

            if (! $anneeAcademique) {
                return;
            }

            foreach (['date_examen', 'date_limite_saisie'] as $field) {
                $value = $this->input($field);

                if (! $value) {
                    continue;
                }

                if ($value < $anneeAcademique->date_debut->format('Y-m-d') || $value > $anneeAcademique->date_fin->format('Y-m-d')) {
                    $validator->errors()->add($field, "Cette date doit être comprise dans la période de l'année académique « {$anneeAcademique->libelle} » ({$anneeAcademique->date_debut->format('d/m/Y')} – {$anneeAcademique->date_fin->format('d/m/Y')}).");
                }
            }
        });
    }
}
