<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreTokenRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personal access tokens, e.g. for connecting an MCP client or a script.
 * The plain-text token is only ever returned once, at creation.
 */
class TokenController extends ApiBaseController
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderByDesc('created_at')->get();

        return $this->respondApiSuccess(PersonalAccessTokenResource::class, $tokens, 'Tokens retrieved successfully');
    }

    public function store(StoreTokenRequest $request): JsonResponse
    {
        $newToken = $request->user()->createToken($request->input('name'));

        return $this->respondApiSuccess(null, [
            'id' => $newToken->accessToken->id,
            'name' => $newToken->accessToken->name,
            'token' => $newToken->plainTextToken,
        ], 'Token created successfully', 201);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()->whereKey($tokenId)->firstOrFail()->delete();

        return $this->respondApiSuccess(null, null, 'Token revoked successfully', 204);
    }
}
