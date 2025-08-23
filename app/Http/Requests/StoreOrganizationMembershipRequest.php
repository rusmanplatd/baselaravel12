<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create organization memberships');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:sys_users,id',
            'membership_type' => 'required|string|in:employee,contractor,board_member,executive',
            'organization_unit_id' => 'nullable|exists:organization_units,id',
            'organization_position_id' => 'nullable|exists:organization_positions,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:active,inactive,terminated',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:sys_roles,name',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user.',
            'user_id.exists' => 'The selected user does not exist.',
            'membership_type.required' => 'Please select a membership type.',
            'membership_type.in' => 'The membership type must be one of: employee, contractor, board member, or executive.',
            'start_date.required' => 'Please provide a start date.',
            'end_date.after' => 'The end date must be after the start date.',
            'roles.*.exists' => 'One or more selected roles do not exist.',
        ];
    }
}
