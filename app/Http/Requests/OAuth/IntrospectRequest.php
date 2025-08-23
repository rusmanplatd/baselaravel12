<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class IntrospectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // OAuth introspection endpoint is public with proper client credentials
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
        ];
    }
}
