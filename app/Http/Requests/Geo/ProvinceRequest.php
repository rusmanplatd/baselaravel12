<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;

class ProvinceRequest extends FormRequest
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
        $provinceId = $this->route('province')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'country_id' => ($isUpdate ? 'sometimes|' : '').'required|string|exists:ref_geo_country,id',
            'code' => ($isUpdate ? 'sometimes|' : '').'required|string|max:10|unique:ref_geo_province,code'.($provinceId ? ",{$provinceId}" : ''),
            'name' => ($isUpdate ? 'sometimes|' : '').'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'country_id.required' => 'Country is required.',
            'country_id.exists' => 'Selected country does not exist.',
            'code.required' => 'Province code is required.',
            'code.unique' => 'Province code must be unique.',
            'name.required' => 'Province name is required.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'country_id' => 'country',
            'code' => 'province code',
            'name' => 'province name',
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
            'country_id' => [
                'description' => 'The ID of the country this province belongs to',
                'example' => '01',
            ],
            'code' => [
                'description' => 'The province code',
                'example' => '11',
            ],
            'name' => [
                'description' => 'The province name',
                'example' => 'DKI Jakarta',
            ],
        ];
    }
}
