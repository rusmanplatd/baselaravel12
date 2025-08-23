<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MfaSetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
            'password' => 'required|string',
            'secret' => 'sometimes|required|string',
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
            'code' => [
                'description' => '6-digit TOTP code from authenticator app',
                'example' => '123456',
            ],
            'password' => [
                'description' => 'User current password for verification',
                'example' => 'password123',
            ],
            'secret' => [
                'description' => 'TOTP secret key (optional, used during setup)',
                'example' => 'JBSWY3DPEHPK3PXP',
            ],
        ];
    }
}
