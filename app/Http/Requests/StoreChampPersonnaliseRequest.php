<?php

namespace App\Http\Requests;

use App\Enums\TypeChampPersonnalise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChampPersonnaliseRequest extends FormRequest
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
            'options_raw' => ['required_if:type,liste_deroulante', 'nullable', 'string'],
            'obligatoire' => ['boolean'],
        ];
    }

    /**
     * Converts the "one option per line" textarea into the array shape
     * stored in `champs_personnalises.options`.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        if (($validated['type'] ?? null) === TypeChampPersonnalise::ListeDeroulante->value) {
            $validated['options'] = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['options_raw'] ?? '')))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all();
        } else {
            $validated['options'] = null;
        }

        unset($validated['options_raw']);

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.required' => 'Le nom du champ est obligatoire.',
            'options_raw.required_if' => 'Indiquez au moins une option pour une liste déroulante.',
        ];
    }
}
