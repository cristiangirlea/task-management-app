<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\ApiBaseController;
use App\Services\OAuth\OAuthConnections;
use App\Services\OAuth\PendingAuthorizations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\User as OAuthUser;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The web app's consent screen: what an MCP client asks for, and the
 * signed-in person's answer. Either answer returns the address to send the
 * browser to, back at the client.
 */
class ConsentController extends ApiBaseController
{
    // The authorization server is resolved per answer: it needs Passport's
    // keys, which `route:list` (it builds every controller) must not.
    public function __construct(protected PendingAuthorizations $pending) {}

    public function show(string $authorization): JsonResponse
    {
        $authRequest = $this->pending->find($authorization);
        if ($authRequest === null) {
            return $this->respondApiError(__('oauth.expired'), 404);
        }

        return $this->respondApiSuccess(null, [
            'client' => [
                'id' => $authRequest->getClient()->getIdentifier(),
                'name' => $authRequest->getClient()->getName(),
                'redirect_host' => OAuthConnections::redirectHost([$this->redirectUri($authRequest)]),
            ],
            'scopes' => collect(Passport::scopesFor($this->scopeIds($authRequest)))
                ->map(fn ($scope): array => ['id' => $scope->id, 'description' => $scope->description])
                ->values(),
        ]);
    }

    public function approve(Request $request, ResponseInterface $psrResponse, AuthorizationServer $server, string $authorization): JsonResponse
    {
        $authRequest = $this->pending->pull($authorization);
        if ($authRequest === null) {
            return $this->respondApiError(__('oauth.expired'), 404);
        }

        $authRequest->setUser(new OAuthUser($request->user()->getAuthIdentifier()));
        $authRequest->setAuthorizationApproved(true);
        $response = $server->completeAuthorizationRequest($authRequest, $psrResponse);

        return $this->respondApiSuccess(null, ['redirect_url' => $response->getHeaderLine('Location')], __('oauth.approved'));
    }

    public function deny(Request $request, ResponseInterface $psrResponse, AuthorizationServer $server, string $authorization): JsonResponse
    {
        $authRequest = $this->pending->pull($authorization);
        if ($authRequest === null) {
            return $this->respondApiError(__('oauth.expired'), 404);
        }

        $authRequest->setUser(new OAuthUser($request->user()->getAuthIdentifier()));
        $authRequest->setAuthorizationApproved(false);

        try {
            $server->completeAuthorizationRequest($authRequest, $psrResponse);
            $location = null;
        } catch (OAuthServerException $e) {
            // access_denied, with the client's state, on its redirect URI.
            $location = $e->generateHttpResponse($psrResponse)->getHeaderLine('Location');
        }

        return $this->respondApiSuccess(null, ['redirect_url' => $location], __('oauth.denied'));
    }

    private function redirectUri(AuthorizationRequestInterface $authRequest): string
    {
        $client = $authRequest->getClient()->getRedirectUri();

        return $authRequest->getRedirectUri() ?? (is_array($client) ? $client[0] : $client);
    }

    /**
     * @return list<string>
     */
    private function scopeIds(AuthorizationRequestInterface $authRequest): array
    {
        return collect($authRequest->getScopes())
            ->map(fn (ScopeEntityInterface $scope): string => $scope->getIdentifier())
            ->unique()
            ->values()
            ->all();
    }
}
