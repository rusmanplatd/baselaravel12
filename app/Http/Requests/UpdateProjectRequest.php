<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $project && Auth::user()?->can('update', $project);
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => 'sometimes|required|in:public,private',
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