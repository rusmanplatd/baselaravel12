<?php

namespace App\Http\Requests\OrganizationUnit;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create organization units');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'unit_code' => 'required|string|unique:organization_units',
            'name' => 'required|string|max:255',
            'unit_type' => 'required|in:board_of_commissioners,board_of_directors,executive_committee,audit_committee,risk_committee,nomination_committee,remuneration_committee,division,department,section,team,branch_office,representative_office',
            'description' => 'nullable|string',
            'parent_unit_id' => 'nullable|exists:organization_units,id',
            'responsibilities' => 'nullable|array',
            'authorities' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
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
            'organization_id' => [
                'description' => 'ID of the organization this unit belongs to',
                'example' => 1,
            ],
            'unit_code' => [
                'description' => 'Unique code for the organization unit',
                'example' => 'HR-001',
            ],
            'name' => [
                'description' => 'Name of the organization unit',
                'example' => 'Human Resources Department',
            ],
            'unit_type' => [
                'description' => 'Type of organization unit',
                'example' => 'department',
            ],
            'description' => [
                'description' => 'Description of the organization unit',
                'example' => 'Handles employee relations and recruitment',
            ],
            'parent_unit_id' => [
                'description' => 'ID of parent organization unit (optional)',
                'example' => 1,
            ],
            'responsibilities' => [
                'description' => 'Array of responsibilities',
                'example' => ['Employee management', 'Recruitment'],
            ],
            'authorities' => [
                'description' => 'Array of authorities',
                'example' => ['Hiring decisions', 'Policy enforcement'],
            ],
            'is_active' => [
                'description' => 'Whether the unit is active',
                'example' => true,
            ],
            'sort_order' => [
                'description' => 'Sort order for display',
                'example' => 1,
            ],
        ];
    }
}
