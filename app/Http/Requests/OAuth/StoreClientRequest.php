<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('oauth.client.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'redirect_uris' => 'required|array|min:1',
            'redirect_uris.*' => 'required|url',
            'organization_id' => 'required|exists:organizations,id',
            'client_type' => 'required|in:web,mobile,desktop,service',
            'user_access_scope' => 'required|in:all_users,organization_members,custom',
            'user_access_rules' => 'required_if:user_access_scope,custom|array|nullable',
            'user_access_rules.user_ids' => 'sometimes|array',
            'user_access_rules.user_ids.*' => 'exists:users,id',
            'user_access_rules.roles' => 'sometimes|array',
            'user_access_rules.organization_roles' => 'sometimes|array',
            'user_access_rules.position_levels' => 'sometimes|array',
            'user_access_rules.email_domains' => 'sometimes|array',
            'user_access_rules.email_domains.*' => 'string|regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'allowed_scopes' => 'sometimes|array',
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
                'description' => 'The name of the OAuth client',
                'example' => 'My Application',
            ],
            'redirect_uris' => [
                'description' => 'Array of redirect URIs for the OAuth client',
                'example' => ['https://myapp.com/oauth/callback'],
            ],
            'organization_id' => [
                'description' => 'The ID of the organization this client belongs to',
                'example' => 1,
            ],
            'client_type' => [
                'description' => 'The type of OAuth client',
                'example' => 'web',
            ],
            'user_access_scope' => [
                'description' => 'Defines which users can access this client',
                'example' => 'organization_members',
            ],
            'user_access_rules' => [
                'description' => 'Custom rules for user access (required if user_access_scope is custom)',
                'example' => [
                    'user_ids' => [1, 2, 3],
                    'roles' => ['admin', 'manager'],
                    'email_domains' => ['company.com'],
                ],
            ],
            'allowed_scopes' => [
                'description' => 'Array of OAuth scopes this client is allowed to request (optional)',
                'example' => ['profile', 'email', 'organization.read'],
            ],
            'description' => [
                'description' => 'Description of the OAuth client (optional)',
                'example' => 'My application for managing users',
            ],
            'website' => [
                'description' => 'Website URL of the client application (optional)',
                'example' => 'https://myapp.com',
            ],
            'logo_url' => [
                'description' => 'URL to the client application logo (optional)',
                'example' => 'https://myapp.com/logo.png',
            ],
        ];
    }
}
