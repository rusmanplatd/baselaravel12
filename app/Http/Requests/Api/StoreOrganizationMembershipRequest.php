<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/gates
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'organization_id' => 'required|exists:organizations,id',
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'membership_type' => 'required|in:employee,board_member,consultant,contractor,intern',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'additional_roles' => 'nullable|array',
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
            'user_id' => [
                'description' => 'ID of the user to create membership for',
                'example' => 1,
            ],
            'organization_id' => [
                'description' => 'ID of the organization',
                'example' => 1,
            ],
            'organization_unit_id' => [
                'description' => 'ID of the organization unit (optional)',
                'example' => 1,
            ],
            'organization_position_id' => [
                'description' => 'ID of the organization position (optional)',
                'example' => 1,
            ],
            'membership_type' => [
                'description' => 'Type of membership',
                'example' => 'employee',
            ],
            'start_date' => [
                'description' => 'Start date of membership',
                'example' => '2024-01-01',
            ],
            'end_date' => [
                'description' => 'End date of membership (optional)',
                'example' => '2024-12-31',
            ],
            'status' => [
                'description' => 'Status of the membership',
                'example' => 'active',
            ],
            'additional_roles' => [
                'description' => 'Array of additional role IDs (optional)',
                'example' => [1, 2],
            ],
        ];
    }
}