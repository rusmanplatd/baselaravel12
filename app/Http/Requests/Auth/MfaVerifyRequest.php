<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MfaVerifyRequest extends FormRequest
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
            'code' => 'required|string',
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
                'description' => 'The 6-digit TOTP code or backup code for verification',
                'example' => '123456',
            ],
        ];
    }
}
