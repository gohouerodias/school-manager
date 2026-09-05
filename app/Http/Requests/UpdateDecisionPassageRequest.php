<?php

namespace App\Http\Requests;

use App\Enums\DecisionAnnuelle;
use App\Models\ParametreSysteme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * US D.3 — la direction peut valider la proposition automatique de passage
 * telle quelle, ou la modifier ; dans ce dernier cas un motif devient
 * obligatoire (voir Academique\DecisionPassageController::update()).
 */
class UpdateDecisionPassageRequest extends FormRequest
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
            'decision' => ['required', Rule::enum(DecisionAnnuelle::class)],
            'motif' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inscription = $this->route('inscription');
            $decision = $this->input('decision');

            if (! $inscription || ! $decision) {
                return;
            }

            $moyenne = $inscription->calculerMoyenneAnnuelle();
            $seuil = ParametreSysteme::query()->value('seuil_passage') ?? 10;
            $proposition = $moyenne >= $seuil ? DecisionAnnuelle::Admis->value : DecisionAnnuelle::Redouble->value;

            if ($decision !== $proposition && ! $this->filled('motif')) {
                $validator->errors()->add('motif', 'Un motif est obligatoire lorsque vous modifiez la proposition automatique.');
            }
        });
    }
}
