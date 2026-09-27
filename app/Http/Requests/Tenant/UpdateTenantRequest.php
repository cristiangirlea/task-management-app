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
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($tenantId),
            ],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenantId),
            ],
            'settings' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'The name field must not be greater than 255 characters.',
            'slug.max' => 'The slug must not be greater than 255 characters.',
            'slug.unique' => 'The slug has already been taken.',
            'domain.max' => 'The domain must not be greater than 255 characters.',
            'domain.unique' => 'The domain has already been taken.',
            'settings.array' => 'The settings field must be an object.',
        ];
    }
}
