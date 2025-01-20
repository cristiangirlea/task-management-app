<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:tenants,slug|max:255',
            'domain' => 'nullable|string|unique:tenants,domain|max:255',
            'settings' => 'nullable|json',
        ];
    }

    public function messages(): array
    {
        return [
            // Name
            'name.required' => 'The name field is required.',
            'name.max' => 'The name field must not be greater than 255 characters.',

            // Slug
            'slug.required' => 'The slug field is required.',
            'slug.max' => 'The slug must not be greater than 255 characters.',
            'slug.unique' => 'The slug has already been taken.',

            // Domain
            'domain.max' => 'The domain must not be greater than 255 characters.',
            'domain.unique' => 'The domain has already been taken.',

            // Settings
            'settings.json' => 'The settings field must be a valid JSON string.',
        ];
    }
}
