<?php

namespace App\Services\OAuth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;

/**
 * An MCP client's authorization request, held between the API checking it
 * and the person allowing or denying it in the web app. The id travels in
 * the web app's URL; only its hash is a cache key.
 */
class PendingAuthorizations
{
    public const TTL_SECONDS = 600;

    public function put(AuthorizationRequestInterface $request): string
    {
        $id = Str::random(40);

        // Stored as a string so the cache never has to unserialize it.
        Cache::put($this->key($id), serialize($request), self::TTL_SECONDS);

        return $id;
    }

    public function find(string $id): ?AuthorizationRequestInterface
    {
        return $this->restore(Cache::get($this->key($id)));
    }

    /**
     * Takes the request out: each one is answered once.
     */
    public function pull(string $id): ?AuthorizationRequestInterface
    {
        return $this->restore(Cache::pull($this->key($id)));
    }

    private function restore(mixed $value): ?AuthorizationRequestInterface
    {
        $request = is_string($value) ? unserialize($value) : null;

        return $request instanceof AuthorizationRequestInterface ? $request : null;
    }

    private function key(string $id): string
    {
        return 'oauth-authorization:'.hash('sha256', $id);
    }
}
