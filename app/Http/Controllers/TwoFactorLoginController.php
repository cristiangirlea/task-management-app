<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\Traits\IssuesAuthTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The second step of signing in: the challenge from POST /login plus a code
 * from the authenticator app, or one of the recovery codes.
 */
class TwoFactorLoginController extends ApiBaseController
{
    use IssuesAuthTokens;

    public function __construct(protected TwoFactorService $twoFactor) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = $this->twoFactor->challengeUser($data['challenge']);
        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['challenge' => __('auth.two_factor.challenge_expired')]);
        }

        $field = filled($data['code'] ?? null) ? 'code' : 'recovery_code';
        $passed = $field === 'code'
            ? $this->twoFactor->verifyCode($user, $data['code'])
            : $this->twoFactor->useRecoveryCode($user, $data['recovery_code']);

        if (! $passed) {
            throw ValidationException::withMessages($this->twoFactor->failChallenge($data['challenge'])
                ? [$field => __('auth.two_factor.invalid_code')]
                : ['challenge' => __('auth.two_factor.too_many_attempts')]);
        }

        $this->twoFactor->finishChallenge($data['challenge']);

        return $this->respondApiSuccess(null, $this->authPayload($user), __('auth.login.success'));
    }
}
