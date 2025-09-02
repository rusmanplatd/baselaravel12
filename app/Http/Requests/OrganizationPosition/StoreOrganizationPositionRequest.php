<?php

namespace App\Http\Requests\OrganizationPosition;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationPositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('org_position:write');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_unit_id' => 'required|exists:organization_units,id',
            'position_code' => 'required|string|unique:organization_positions|max:50',
            'title' => 'required|string|max:255',
            'position_level' => 'required|in:board_member,c_level,vice_president,director,senior_manager,manager,assistant_manager,supervisor,senior_staff,staff,junior_staff',
            'job_description' => 'nullable|string|max:5000',
            'qualifications' => 'nullable|array|max:20',
            'qualifications.*' => 'required|string|max:500',
            'responsibilities' => 'nullable|array|max:20',
            'responsibilities.*' => 'required|string|max:500',
            'min_salary' => 'nullable|numeric|min:0|max:99999999.99',
            'max_salary' => 'nullable|numeric|min:0|max:99999999.99|gte:min_salary',
            'is_active' => 'boolean',
            'max_incumbents' => 'integer|min:1|max:100',
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
            'organization_unit_id' => [
                'description' => 'ID of the organization unit this position belongs to',
                'example' => 1,
            ],
            'position_code' => [
                'description' => 'Unique code for the position',
                'example' => 'HR-DIR-001',
            ],
            'title' => [
                'description' => 'Title of the position',
                'example' => 'HR Director',
            ],
            'position_level' => [
                'description' => 'Level of the position in organizational hierarchy',
                'example' => 'director',
            ],
            'job_description' => [
                'description' => 'Detailed job description',
                'example' => 'Responsible for managing HR operations and strategy',
            ],
            'qualifications' => [
                'description' => 'Array of required qualifications',
                'example' => ['Bachelor degree in HR', 'Minimum 5 years experience'],
            ],
            'responsibilities' => [
                'description' => 'Array of key responsibilities',
                'example' => ['Team management', 'Strategic planning'],
            ],
            'min_salary' => [
                'description' => 'Minimum salary for this position',
                'example' => 50000,
            ],
            'max_salary' => [
                'description' => 'Maximum salary for this position',
                'example' => 80000,
            ],
            'is_active' => [
                'description' => 'Whether the position is active',
                'example' => true,
            ],
            'max_incumbents' => [
                'description' => 'Maximum number of people who can hold this position',
                'example' => 1,
            ],
        ];
    }
}
