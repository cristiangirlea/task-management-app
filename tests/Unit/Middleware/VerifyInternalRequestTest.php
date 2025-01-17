<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\VerifyInternalRequest;

class VerifyInternalRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushAll(); // Clear Redis to avoid interference
    }

    public function test_allows_request_in_non_production_environment()
    {
        // Mock a non-production environment
        config(['app.env' => 'local']);

        $middleware = new VerifyInternalRequest();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->status());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_blocks_request_missing_headers()
    {
        config(['app.env' => 'production']);

        $middleware = new VerifyInternalRequest();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->status());
        $this->assertJson($response->getContent());
        $this->assertStringContainsString('Unauthorized - Missing headers', $response->getContent());
    }

    public function test_blocks_request_with_invalid_signature()
    {
        config(['app.env' => 'production']);
        config(['app.api_secret_key' => 'secret']);

        $middleware = new VerifyInternalRequest();
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-TIMESTAMP' => time(),
            'HTTP_X-SIGNATURE' => 'invalid-signature',
            'HTTP_X-NONCE' => 'nonce123',
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->status());
        $this->assertJson($response->getContent());
        $this->assertStringContainsString('Invalid signature', $response->getContent());
    }

    public function test_blocks_replay_attack_with_used_nonce()
    {
        config(['app.env' => 'production']);
        config(['app.api_secret_key' => 'secret']);

        $nonce = 'nonce123';
        Redis::set("nonce:$nonce", true);

        $middleware = new VerifyInternalRequest();
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-TIMESTAMP' => time(),
            'HTTP_X-SIGNATURE' => hash_hmac('sha256', time() . $nonce, 'secret'),
            'HTTP_X-NONCE' => $nonce,
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->status());
        $this->assertJson($response->getContent());
        $this->assertStringContainsString('Replay attack detected', $response->getContent());
    }

    public function test_allows_request_with_valid_headers()
    {
        config(['app.env' => 'production']);
        config(['app.api_secret_key' => 'secret']);

        $timestamp = time();
        $nonce = 'nonce123';
        $signature = hash_hmac('sha256', $timestamp . $nonce, 'secret');

        $middleware = new VerifyInternalRequest();
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-TIMESTAMP' => $timestamp,
            'HTTP_X-SIGNATURE' => $signature,
            'HTTP_X-NONCE' => $nonce,
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->status());
        $this->assertEquals('OK', $response->getContent());
    }
}
