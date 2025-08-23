<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // User must be authenticated to reach this controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Get the body parameters for API documentation.
     *
     * @return array<string, array>
     */
    public function bodyParameters(): array
    {
        return [
            'current_password' => [
                'description' => 'The user\'s current password for verification',
                'example' => 'current-password-123',
            ],
            'password' => [
                'description' => 'The new password (must meet security requirements)',
                'example' => 'new-secure-password-456',
            ],
            'password_confirmation' => [
                'description' => 'Confirmation of the new password',
                'example' => 'new-secure-password-456',
            ],
        ];
    }
}
