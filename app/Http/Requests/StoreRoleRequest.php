<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage roles');
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
            'team_id' => 'nullable|exists:organizations,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:sys_permissions,name',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a role name.',
            'name.max' => 'The role name cannot be longer than 255 characters.',
            'team_id.exists' => 'The selected organization does not exist.',
            'permissions.array' => 'Permissions must be provided as an array.',
            'permissions.*.exists' => 'One or more selected permissions do not exist.',
        ];
    }
}
