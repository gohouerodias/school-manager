<?php

namespace App\Http\Requests;

use App\Models\TypeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDocumentEleveRequest extends FormRequest
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
            'type_document_id' => ['required', 'exists:types_documents,id'],
            'fichier' => ['required', 'file', 'max:5120'],
        ];
    }

    /**
     * Cross-checks the uploaded file's extension against the selected
     * document type's `formats_acceptes` (e.g. a "Photo d'identité" type
     * configured for JPG/PNG shouldn't silently accept a PDF).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $typeId = $this->input('type_document_id');
            $fichier = $this->file('fichier');

            if (! $typeId || ! $fichier || ! $fichier->isValid()) {
                return;
            }

            $type = TypeDocument::find($typeId);
            $accepted = array_map('strtoupper', $type?->formats_acceptes ?? []);

            if ($accepted === []) {
                return;
            }

            $extension = strtoupper($fichier->getClientOriginalExtension());

            if (! in_array($extension, $accepted, true)) {
                $validator->errors()->add(
                    'fichier',
                    'Format non accepté pour ce type de document. Formats attendus : '.implode(', ', $accepted).'.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type_document_id.required' => 'Sélectionnez un type de document.',
            'fichier.required' => 'Sélectionnez un fichier à téléverser.',
            'fichier.max' => 'Le fichier dépasse la taille maximale de 5 Mo.',
        ];
    }
}
