<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Skip verification for development environments
        $appEnv = config('app.env');
        if (in_array($appEnv, ['local', 'dev', 'development'])) {
            return $next($request); // Skip verification in non-production environments
        }

        $apiKey = config('app.api_secret_key'); // Retrieve API key from .env
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');
        $nonce = $request->header('X-NONCE'); // Nonce header

        // Ensure all required headers exist
        if (!$timestamp || !$signature || !$nonce) {
            return response()->json(['error' => 'Unauthorized - Missing headers'], Response::HTTP_UNAUTHORIZED);
        }

        if (abs(time() - (int)$timestamp) > config('app.nonce_expiration')) {
            return response()->json(['error' => 'Request expired'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if nonce has already been used (replay protection)
        if (Redis::get("nonce:$nonce")) {
            return response()->json(['error' => 'Replay attack detected'], Response::HTTP_UNAUTHORIZED);
        }

        // Store nonce in Redis with config expirations in seconds
        Redis::set("nonce:$nonce", true, 'EX', config('app.nonce_expiration')); // EX sets expiry to 60 seconds

        // Recreate the signature using timestamp and nonce
        $expectedSignature = hash_hmac('sha256', $timestamp . $nonce, $apiKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request); // Proceed if all checks pass
    }
}
