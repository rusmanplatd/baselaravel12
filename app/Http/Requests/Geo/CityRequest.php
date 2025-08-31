<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;

class CityRequest extends FormRequest
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
        $cityId = $this->route('city')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'province_id' => ($isUpdate ? 'sometimes|' : '') . 'required|string|exists:ref_geo_province,id',
            'code' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:10|unique:ref_geo_city,code' . ($cityId ? ",{$cityId}" : ''),
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'province_id.required' => 'Province is required.',
            'province_id.exists' => 'Selected province does not exist.',
            'code.required' => 'City code is required.',
            'code.unique' => 'City code must be unique.',
            'name.required' => 'City name is required.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'province_id' => 'province',
            'code' => 'city code',
            'name' => 'city name',
        ];
    }
}
