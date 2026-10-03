<?php

namespace App\Http\Controllers\OAuth;

use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /oauth/token, Passport's own, which needs Passport's keys to be built.
 * Resolving it per request keeps `route:list` (it builds every controller)
 * working on a server without them.
 */
class TokenController
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, AccessTokenController $passport): Response
    {
        return $passport->issueToken($request, $response);
    }
}
