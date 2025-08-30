<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class AuthorizeRequest extends FormRequest
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
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'response_type' => 'required|string|in:code',
            'scope' => 'required|string', // Google requires scopes
            'state' => 'required|string', // Google strongly recommends state for security
            // PKCE parameters (required for public clients like Google)
            'code_challenge' => 'required_without:client_secret|string|min:43|max:128',
            'code_challenge_method' => 'required_with:code_challenge|string|in:S256', // Only S256, no plain
            // Google-style incremental authorization (required when existing consent)
            'include_granted_scopes' => 'string|in:true,false',
            'approval_prompt' => 'sometimes|string|in:force', // Only 'force' is valid, 'auto' is default
            // Google OAuth 2.0 parameters
            'access_type' => 'sometimes|string|in:online,offline',
            'prompt' => 'sometimes|string|in:none,consent,select_account',
        ];
    }

    public function messages(): array
    {
        return [
            'scope.required' => 'The scope parameter is required.',
            'state.required' => 'The state parameter is required for security.',
            'code_challenge_method.in' => 'Only S256 code challenge method is supported.',
            'include_granted_scopes.in' => 'include_granted_scopes must be "true" or "false".',
        ];
    }
}
