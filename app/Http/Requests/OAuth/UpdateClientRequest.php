<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit oauth clients');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'redirect_uris' => 'sometimes|array|min:1',
            'redirect_uris.*' => 'required|url',
            'description' => 'sometimes|string|max:1000',
            'website' => 'sometimes|url',
            'logo_url' => 'sometimes|url',
        ];
    }
}
