<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;

class VillageRequest extends FormRequest
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
        $villageId = $this->route('village')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'district_id' => ($isUpdate ? 'sometimes|' : '') . 'required|string|exists:ref_district,id',
            'code' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:10|unique:ref_village,code' . ($villageId ? ",{$villageId}" : ''),
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'district_id.required' => 'District is required.',
            'district_id.exists' => 'Selected district does not exist.',
            'code.required' => 'Village code is required.',
            'code.unique' => 'Village code must be unique.',
            'name.required' => 'Village name is required.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'district_id' => 'district',
            'code' => 'village code',
            'name' => 'village name',
        ];
    }
}
