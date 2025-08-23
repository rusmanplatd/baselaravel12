<?php

namespace App\Http\Requests\OrganizationMembership;

use Illuminate\Foundation\Http\FormRequest;

class TerminateOrganizationMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('terminate organization memberships');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'end_date' => 'nullable|date',
        ];
    }
}
