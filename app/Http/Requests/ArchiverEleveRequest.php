<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiverEleveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is already gated by the eleves routes' profile middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motif.required' => "Indiquez le motif de l'archivage.",
            'motif.max' => 'Le motif ne doit pas dépasser 255 caractères.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motif' => trim((string) $this->input('motif', ''))]);
    }
}
