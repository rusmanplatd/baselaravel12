<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;

class CountryRequest extends FormRequest
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
        $countryId = $this->route('country')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'code' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:10|unique:ref_country,code' . ($countryId ? ",{$countryId}" : ''),
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'iso_code' => 'nullable|string|max:3|unique:ref_country,iso_code' . ($countryId ? ",{$countryId}" : ''),
            'phone_code' => 'nullable|string|max:10',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Country code is required.',
            'code.unique' => 'Country code must be unique.',
            'name.required' => 'Country name is required.',
            'iso_code.unique' => 'ISO code must be unique.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'code' => 'country code',
            'name' => 'country name',
            'iso_code' => 'ISO code',
            'phone_code' => 'phone code',
        ];
    }
}
