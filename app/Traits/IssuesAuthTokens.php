<?php

namespace App\Traits;

use App\Http\Resources\UserResource;
use App\Models\User;

trait IssuesAuthTokens
{
    /**
     * What a successful sign-in returns: the user and a new API token.
     *
     * @return array{user: UserResource, token: string}
     */
    protected function authPayload(User $user): array
    {
        return [
            'user' => new UserResource($user->load('tenant')),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ];
    }
}
