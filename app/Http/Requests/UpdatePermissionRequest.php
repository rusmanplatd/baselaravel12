<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit permissions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s:_-]+$/',
                Rule::unique('sys_permissions', 'name')->ignore($this->route('permission')),
            ],
            'guard_name' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a permission name.',
            'name.max' => 'The permission name cannot be longer than 255 characters.',
            'name.unique' => 'A permission with this name already exists.',
            'name.regex' => 'The permission name may only contain letters, numbers, spaces, colons, underscores, and hyphens.',
            'guard_name.max' => 'The guard name cannot be longer than 255 characters.',
        ];
    }
}
