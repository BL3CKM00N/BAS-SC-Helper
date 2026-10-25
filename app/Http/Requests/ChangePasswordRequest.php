<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Changing your own password. Problems send the person back to the password section, not the top of the page.
 */
class ChangePasswordRequest extends FormRequest
{
    protected $errorBag = 'password';

    protected function getRedirectUrl(): string
    {
        return route('settings.edit').'#password';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The current password is not correct.',
            'password.different' => 'Choose a password that is different from your current one.',
        ];
    }
}
