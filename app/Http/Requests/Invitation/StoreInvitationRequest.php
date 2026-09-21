<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Owners only. This runs before the rules below, so a member cannot use
     * the uniqueness checks to discover which addresses have accounts.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('manage', $user->tenant);
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
