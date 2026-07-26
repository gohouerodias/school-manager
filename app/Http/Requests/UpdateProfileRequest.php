<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a user editing their own name/telephone from the "Mon profil"
 * popover. Deliberately excludes e-mail: a user may never change their own
 * e-mail address, only an administrator can (see UpdateAccountRequest /
 * UserAccountController::update).
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
