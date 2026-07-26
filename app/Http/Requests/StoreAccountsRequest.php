<?php

namespace App\Http\Requests;

use App\Enums\ProfilUtilisateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access to this feature is already gated by the 'profile:administrateur' route middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invites' => ['required', 'array', 'min:1'],
            'invites.*.email' => ['required', 'email', 'distinct', 'unique:users,email'],
            'invites.*.name' => ['required', 'string', 'max:150'],
            'invites.*.telephone' => ['required', 'string', 'max:30'],
            'invites.*.profil' => ['required', Rule::enum(ProfilUtilisateur::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invites.required' => 'Ajoutez au moins un utilisateur à la liste avant d\'enregistrer.',
            'invites.*.email.unique' => 'Un compte existe déjà avec l\'adresse :input.',
            'invites.*.email.distinct' => 'Cette adresse apparaît plusieurs fois dans la liste.',
            'invites.*.name.required' => 'Le nom complet est obligatoire.',
            'invites.*.telephone.required' => 'Le numéro de téléphone est obligatoire.',
        ];
    }
}
