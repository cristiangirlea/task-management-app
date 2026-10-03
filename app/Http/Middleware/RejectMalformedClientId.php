<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth client ids are UUIDs, and PostgreSQL refuses to compare a uuid
 * column with anything else: Passport's lookup would fail as a server error.
 * A malformed client_id gets OAuth's invalid_client answer instead.
 */
class RejectMalformedClientId
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->input('client_id');

        if ($clientId !== null && ! (is_string($clientId) && Str::isUuid($clientId))) {
            return response()->json(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], 401);
        }

        return $next($request);
    }
}
