<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('tenants', 'slug')->ignore($this->tenant),
            ],
            'domain' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($this->tenant),
            ],
            'settings' => 'nullable|json',
        ];
    }

    public function messages(): array
    {
        return [
            // Name
            'name.max' => 'The name field must not be greater than 255 characters.',

            // Slug
            'slug.max' => 'The slug must not be greater than 255 characters.',
            'slug.unique' => 'The slug has already been taken.',

            // Domain
            'domain.max' => 'The domain must not be greater than 255 characters.',
            'domain.unique' => 'The domain has already been taken.',

            // Settings
            'settings.json' => 'The settings field must be a valid JSON string.',
        ];
    }

    /**
     * Get the tenant model from the route.
     */
    public function tenant(): \App\Models\Tenant
    {
        return $this->route('tenant');
    }
}
