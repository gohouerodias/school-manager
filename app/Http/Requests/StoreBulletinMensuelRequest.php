<?php

namespace App\Http\Requests;

use App\Enums\ResultatMensuel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * The monthly bulletin's overall rating + comment — reserved to the
 * classe's titulaire (see Classe::titulairePour(), enforced in
 * Enseignant\EspaceEnseignantController::saveBulletin(), never trust a
 * client-side lock alone).
 */
class StoreBulletinMensuelRequest extends FormRequest
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
            'resultat_global' => ['nullable', new Enum(ResultatMensuel::class)],
            'appreciation' => ['nullable', 'string', 'max:2000'],
            'assiduite' => ['nullable', 'string', 'max:255'],
            'conduite' => ['nullable', 'string', 'max:255'],
            'defauts_majeurs' => ['nullable', 'string', 'max:255'],
            'qualites' => ['nullable', 'string', 'max:255'],
            'decision_pedagogique' => ['nullable', 'string', 'max:255'],
        ];
    }
}
