<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends ApiBaseController
{
    /**
     * Email a reset link.
     *
     * The response is deliberately identical whether or not the address has an
     * account, so this endpoint cannot be used to discover who is registered.
     */
    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->respondApiSuccess(null, null, __('passwords.sent'));
    }

    /**
     * Set a new password and sign every existing session out: a reset is the
     * remedy for a compromised account, so old bearer tokens must stop working.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->respondApiSuccess(null, null, __('passwords.reset'));
    }
}
