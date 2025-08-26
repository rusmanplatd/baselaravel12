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
        return $this->user()->can('create oauth clients');
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
            'client_type' => 'required|in:public,confidential',
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
}
