<?php

namespace Tests\Fakes;

use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

/**
 * Stands in for Stripe's HTTP API. TestCase installs one for every test, so
 * a test that reaches Stripe without saying so fails at once instead of
 * making a network call: an unstubbed request behaves like Stripe being
 * down. Tests that exercise the real Cashier code stub the responses they
 * need with on().
 */
class StripeStub implements ClientInterface
{
    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    private array $responses = [];

    /** @var list<array{method: string, path: string, params: array<string, mixed>}> */
    public array $requests = [];

    public static function install(): self
    {
        $stub = new self;
        ApiRequestor::setHttpClient($stub);

        return $stub;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function on(string $method, string $path, array $body, int $status = 200): self
    {
        $this->responses[strtoupper($method).' '.$path] = [$status, $body];

        return $this;
    }

    /**
     * @return list<string> "METHOD /path" of every request, in order
     */
    public function calls(): array
    {
        return array_map(fn (array $r) => "{$r['method']} {$r['path']}", $this->requests);
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        $key = strtoupper($method).' '.$path;
        $this->requests[] = ['method' => strtoupper($method), 'path' => $path, 'params' => (array) $params];

        if (! isset($this->responses[$key])) {
            throw new ApiConnectionException("Stripe is unreachable in tests (no stub for {$key}).");
        }

        [$status, $body] = $this->responses[$key];

        return [json_encode($body, JSON_THROW_ON_ERROR), $status, []];
    }
}
