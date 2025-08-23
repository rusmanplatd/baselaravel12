<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class OidcTokenRequest extends FormRequest
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
            'grant_type' => 'required|string',
            'client_id' => 'required|string',
            'code' => 'required_if:grant_type,authorization_code',
            'redirect_uri' => 'required_if:grant_type,authorization_code',
            'code_verifier' => 'sometimes|string',
        ];
    }
}
