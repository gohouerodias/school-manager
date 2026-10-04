<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTypeDocumentRequest extends FormRequest
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
            'libelle' => ['required', 'string', 'max:150', Rule::unique('types_documents', 'libelle')->ignore($this->route('typeDocument'))],
            'formats_acceptes' => ['required', 'array', 'min:1'],
            'formats_acceptes.*' => [Rule::in(['PDF', 'JPG', 'PNG'])],
            'obligatoire' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.required' => 'Le nom du type de document est obligatoire.',
            'libelle.unique' => 'Un type de document porte déjà ce nom.',
            'formats_acceptes.required' => 'Sélectionnez au moins un format accepté.',
        ];
    }
}
