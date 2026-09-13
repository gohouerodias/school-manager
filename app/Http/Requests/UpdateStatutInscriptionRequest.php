<?php

namespace App\Http\Requests;

use App\Enums\StatutInscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatutInscriptionRequest extends FormRequest
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
            'statut' => ['required', Rule::enum(StatutInscription::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statut.required' => 'Le statut de cette année est obligatoire.',
            'statut.enum' => 'Statut invalide.',
        ];
    }
}
