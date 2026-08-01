<?php

namespace App\Http\Requests;

use App\Enums\TypeChampPersonnalise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChampPersonnaliseRequest extends FormRequest
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
            'libelle' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(TypeChampPersonnalise::class)],
            'options' => ['required_if:type,liste_deroulante', 'nullable', 'array', 'min:1'],
            'options.*' => ['string', 'max:100'],
            'obligatoire' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.required' => 'Le nom du champ est obligatoire.',
            'options.required_if' => 'Indiquez au moins une option pour une liste déroulante.',
        ];
    }
}
