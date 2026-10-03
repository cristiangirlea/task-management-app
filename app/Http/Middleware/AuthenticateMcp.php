<?php

namespace App\Http\Middleware;

use App\Services\OAuth\OAuthKeys;
use Illuminate\Auth\Middleware\Authenticate;

/**
 * The MCP server takes a Sanctum token, or an OAuth access token when OAuth
 * is set up. Passport's guard cannot even be built without its keys, so it
 * is only tried when they are there; otherwise a request without a valid
 * Sanctum token would fail with a server error instead of a 401.
 */
class AuthenticateMcp extends Authenticate
{
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, OAuthKeys::present() ? ['sanctum', 'api'] : ['sanctum']);
    }
}
