<?php

namespace App\Http\Controllers\OAuth;

use App\Services\OAuth\PendingAuthorizations;
use Laravel\Passport\Http\Controllers\ConvertsPsrResponses;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where an MCP client sends someone to grant it access (GET /oauth/authorize).
 * The request is checked here, then the browser goes on to the web app,
 * which signs them in if needed and asks them to allow or deny it.
 */
class AuthorizationController
{
    use ConvertsPsrResponses;

    public function __construct(protected PendingAuthorizations $pending) {}

    // The server is resolved per request: it needs Passport's keys, which
    // `route:list` (it builds every controller) must not.
    public function __invoke(ServerRequestInterface $psrRequest, ResponseInterface $psrResponse, AuthorizationServer $server): Response
    {
        try {
            $authRequest = $server->validateAuthorizationRequest($psrRequest);
        } catch (OAuthServerException $e) {
            // Back to the client when its redirect URI checked out; otherwise
            // an error here, never a redirect to an unregistered address.
            return $this->convertResponse($e->generateHttpResponse($psrResponse));
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontend.'/authorize?request='.$this->pending->put($authRequest));
    }
}
