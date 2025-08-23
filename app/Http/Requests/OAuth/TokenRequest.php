<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class TokenRequest extends FormRequest
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
            'grant_type' => 'required|string|in:authorization_code,client_credentials,refresh_token',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'code' => 'required_if:grant_type,authorization_code|string',
            'redirect_uri' => 'required_if:grant_type,authorization_code|url',
            'refresh_token' => 'required_if:grant_type,refresh_token|string',
            'scope' => 'nullable|string',
        ];
    }
}
