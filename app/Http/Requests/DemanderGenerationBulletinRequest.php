<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DemanderGenerationBulletinRequest extends FormRequest
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
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'examen_id' => ['required', 'integer', 'exists:examens,id'],
        ];
    }
}
