<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('oauth_app:write');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'redirect_uris' => 'sometimes|array|min:1',
            'redirect_uris.*' => 'required|url',
            'description' => 'sometimes|string|max:1000',
            'website' => 'sometimes|url',
            'logo_url' => 'sometimes|url',
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
            'name' => [
                'description' => 'The name of the OAuth client (optional)',
                'example' => 'Updated Application Name',
            ],
            'redirect_uris' => [
                'description' => 'Array of redirect URIs for the OAuth client (optional)',
                'example' => ['https://myapp.com/oauth/callback', 'https://myapp.com/oauth/callback2'],
            ],
            'description' => [
                'description' => 'Description of the OAuth client (optional)',
                'example' => 'Updated description for my application',
            ],
            'website' => [
                'description' => 'Website URL of the client application (optional)',
                'example' => 'https://myupdatedapp.com',
            ],
            'logo_url' => [
                'description' => 'URL to the client application logo (optional)',
                'example' => 'https://myupdatedapp.com/new-logo.png',
            ],
        ];
    }
}
