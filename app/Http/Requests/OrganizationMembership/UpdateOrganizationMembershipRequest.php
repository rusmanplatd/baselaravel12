<?php

namespace App\Http\Requests\OrganizationMembership;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit organization memberships');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'membership_type' => 'required|in:employee,board_member,consultant,contractor,intern',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'additional_roles' => 'nullable|array',
        ];
    }
}
