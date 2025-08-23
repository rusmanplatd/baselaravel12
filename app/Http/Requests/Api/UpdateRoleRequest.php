<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit roles');
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
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:sys_permissions,name',
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
                'description' => 'The name of the role',
                'example' => 'manager',
            ],
            'permissions' => [
                'description' => 'Array of permission names to assign to this role',
                'example' => ['organization:read', 'organization:write'],
            ],
        ];
    }
}
