<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Skip verification for development environments
        $appEnv = env('APP_ENV');

        if (in_array($appEnv, ['local', 'dev', 'development'])) {
            return $next($request); // Skip verification
        }

        $apiKey = config('services.node_api_key'); // Retrieve API key from .env
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        // Ensure all required headers exist
        if (!$apiKey || !$timestamp || !$signature) {
            return response()->json(['error' => 'Unauthorized - Missing headers'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if the timestamp is within 60 seconds to prevent replay attacks
        if (abs(time() * 1000 - (int)$timestamp) > 60000) {
            return response()->json(['error' => 'Request expired'], Response::HTTP_UNAUTHORIZED);
        }

        // Recreate the signature
        $expectedSignature = hash_hmac('sha256', $timestamp, $apiKey);
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request); // Proceed if all checks pass
    }
}
