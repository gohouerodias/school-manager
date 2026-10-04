<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportElevesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access to this feature is already gated by route middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'extensions:xlsx,xls,csv', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichier.required' => 'Veuillez sélectionner un fichier à importer.',
            'fichier.mimes' => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
            'fichier.extensions' => 'Le fichier doit être au format Excel (.xlsx, .xls) ou CSV.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
