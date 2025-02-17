<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize()
    {
        return true;  // Allow all requests for now
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000', // Description is optional, must be a string, and max 1000 characters
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Project name is required.',
            'description.string' => 'The description must be a valid string.',
            'description.max' => 'The description cannot exceed 1000 characters.',
        ];
    }
}
