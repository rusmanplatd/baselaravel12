<?php

namespace App\Http\Requests\OrganizationPositionLevel;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationPositionLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create organization position levels');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255|unique:organization_position_levels',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hierarchy_level' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer',
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
                'description' => 'Unique code for the position level',
                'example' => 'L1',
            ],
            'name' => [
                'description' => 'Name of the position level',
                'example' => 'Entry Level',
            ],
            'description' => [
                'description' => 'Description of the position level',
                'example' => 'Entry level positions for new hires',
            ],
            'hierarchy_level' => [
                'description' => 'Hierarchical level number',
                'example' => 1,
            ],
            'is_active' => [
                'description' => 'Whether the position level is active',
                'example' => true,
            ],
            'sort_order' => [
                'description' => 'Sort order for display',
                'example' => 1,
            ],
        ];
    }
}
