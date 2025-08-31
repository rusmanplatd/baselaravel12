<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;

class DistrictRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $districtId = $this->route('district')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'city_id' => ($isUpdate ? 'sometimes|' : '').'required|string|exists:ref_geo_city,id',
            'code' => ($isUpdate ? 'sometimes|' : '').'required|string|max:10|unique:ref_geo_district,code'.($districtId ? ",{$districtId}" : ''),
            'name' => ($isUpdate ? 'sometimes|' : '').'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'city_id.required' => 'City is required.',
            'city_id.exists' => 'Selected city does not exist.',
            'code.required' => 'District code is required.',
            'code.unique' => 'District code must be unique.',
            'name.required' => 'District name is required.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'city_id' => 'city',
            'code' => 'district code',
            'name' => 'district name',
        ];
    }
}
