<?php

namespace App\Http\Requests;

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
            'membership_type' => 'required|string|in:employee,contractor,board_member,executive',
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'roles' => 'nullable|array',
            'roles.*' => 'string',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'membership_type.required' => 'Please select a membership type.',
            'membership_type.in' => 'The membership type must be one of: employee, contractor, board member, or executive.',
            'start_date.required' => 'Please provide a start date.',
            'end_date.after' => 'The end date must be after the start date.',
            'status.in' => 'The status must be active, inactive, or terminated.',
        ];
    }
}
