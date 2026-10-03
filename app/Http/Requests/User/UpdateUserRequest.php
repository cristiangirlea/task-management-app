<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'password' => 'sometimes|required|string|min:8|confirmed',
            // A token alone (a stolen one, or an API token) must not be enough
            // to take the account over: the password or the address it resets to.
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->has('password') || $this->changesEmail()),
                'string',
                'current_password:sanctum',
            ],
        ];
    }

    public function changesEmail(): bool
    {
        return $this->has('email') && $this->input('email') !== $this->user()->email;
    }
}
