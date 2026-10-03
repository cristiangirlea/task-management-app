<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\ApiBaseController;
use App\Services\OAuth\OAuthConnections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The MCP clients someone has allowed into their account, and taking that
 * access back.
 */
class ConnectionController extends ApiBaseController
{
    public function __construct(protected OAuthConnections $connections) {}

    public function index(Request $request): JsonResponse
    {
        return $this->respondApiSuccess(null, $this->connections->forUser($request->user())->all());
    }

    public function destroy(Request $request, string $client): JsonResponse
    {
        // Client ids are UUIDs; PostgreSQL will not compare its uuid column with anything else.
        if (! Str::isUuid($client) || ! $this->connections->disconnect($request->user(), $client)) {
            return $this->respondApiError(__('oauth.not_connected'), 404);
        }

        return $this->respondApiSuccess(null, null, __('oauth.disconnected'));
    }
}
