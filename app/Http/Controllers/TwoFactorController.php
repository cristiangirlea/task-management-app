<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Turning two-factor authentication on and off for the signed-in user.
 * Everything except confirming a code asks for the password again, so a
 * stolen session token alone cannot change it.
 */
class TwoFactorController extends ApiBaseController
{
    public function __construct(protected TwoFactorService $twoFactor) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:sanctum']]);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->respondApiError(__('auth.two_factor.already_enabled'), 409);
        }

        return $this->respondApiSuccess(null, $this->twoFactor->begin($user), __('auth.two_factor.started'));
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->respondApiError(__('auth.two_factor.already_enabled'), 409);
        }
        if ($user->two_factor_secret === null) {
            return $this->respondApiError(__('auth.two_factor.not_started'), 409);
        }

        $codes = $this->twoFactor->confirm($user, $request->input('code'));
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => __('auth.two_factor.invalid_code')]);
        }

        return $this->respondApiSuccess(null, [
            'recovery_codes' => $codes,
            'user' => new UserResource($user->load('tenant')),
        ], __('auth.two_factor.enabled'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:sanctum']]);
        $user = $request->user();

        $this->twoFactor->disable($user);

        return $this->respondApiSuccess(UserResource::class, $user->load('tenant'), __('auth.two_factor.disabled'));
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password:sanctum']]);
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return $this->respondApiError(__('auth.two_factor.not_enabled'), 409);
        }

        return $this->respondApiSuccess(null, [
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user),
        ], __('auth.two_factor.recovery_codes'));
    }
}
