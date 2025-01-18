<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')->id;

        return [
            'name' => 'required|string|max:255|unique:tenants,name,' . $tenantId,
            'domain' => 'nullable|string|max:255|unique:tenants,domain,' . $tenantId,
        ];
    }
}
