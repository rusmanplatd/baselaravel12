<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'scopes' => 'required|array',
            'state' => 'sometimes|string',
            'response_type' => 'required|in:code,token',
            'code_challenge' => 'sometimes|string|min:43|max:128',
            'code_challenge_method' => 'sometimes|string|in:plain,S256',
            // Google-style incremental authorization
            'include_granted_scopes' => 'nullable|string|in:true,false',
        ];
    }
}
