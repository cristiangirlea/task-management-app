<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                // Accounts are global (one workspace per email) ...
                Rule::unique('users', 'email'),
                // ... and one live invitation per email per workspace.
                Rule::unique('invitations', 'email')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('accepted_at')
                    ->where(fn ($query) => $query->where('expires_at', '>', now())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('invitation.validation.email_taken'),
        ];
    }
}
