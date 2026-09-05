<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valider ou dévalider un bulletin mensuel (US C.2/C.3) — réservé au
 * titulaire de la classe, enforced in Enseignant\
 * EspaceEnseignantController::validerBulletin()/devaliderBulletin().
 */
class ValiderBulletinRequest extends FormRequest
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
            'eleve_id' => ['required', 'exists:eleves,id'],
            'examen_id' => ['required', 'exists:examens,id'],
        ];
    }
}
