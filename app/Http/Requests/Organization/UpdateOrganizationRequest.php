<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('org:write');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = $this->route('organization')->id ?? null;

        return [
            'organization_code' => 'nullable|string|unique:organizations,organization_code,'.$organizationId,
            'name' => 'required|string|max:255',
            'organization_type' => 'required|in:holding_company,subsidiary,division,branch,department,unit',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'authorized_capital' => 'nullable|numeric|min:0',
            'paid_capital' => 'nullable|numeric|min:0',
            'establishment_date' => 'nullable|date',
            'legal_status' => 'nullable|string|max:100',
            'business_activities' => 'nullable|string',
            'governance_structure' => 'nullable|array',
            'contact_persons' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $organizationId = $this->route('organization')->id ?? null;
            if (isset($this->parent_organization_id) && $this->parent_organization_id == $organizationId) {
                $validator->errors()->add('parent_organization_id', 'Organization cannot be its own parent');
            }
        });
    }
}
