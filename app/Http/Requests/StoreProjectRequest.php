<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->can('projects.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => 'required|in:public,private',
            'organization_id' => 'required|exists:organizations,id',
            'settings' => 'nullable|array',
            'settings.auto_close_items' => 'nullable|boolean',
            'settings.allow_public_items' => 'nullable|boolean',
            'settings.default_item_type' => 'nullable|in:issue,pull_request,draft_issue',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Project title is required.',
            'title.max' => 'Project title cannot be longer than 255 characters.',
            'description.max' => 'Project description cannot be longer than 2000 characters.',
            'visibility.required' => 'Project visibility is required.',
            'visibility.in' => 'Project visibility must be either public or private.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure settings is an object/array
        if ($this->has('settings') && is_string($this->settings)) {
            $this->merge([
                'settings' => json_decode($this->settings, true) ?: []
            ]);
        }
    }
}