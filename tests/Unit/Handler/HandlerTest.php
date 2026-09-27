<?php

namespace Tests\Unit\Handler;

use App\Exceptions\Handler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

class HandlerTest extends TestCase
{
    /**
     * Simulate the handler exception.
     */
    private function renderApiException(Throwable $exception, string $uri = '/api/test'): TestResponse
    {
        $handler = app(Handler::class);
        $request = Request::create($uri, 'GET');
        $response = $handler->render($request, $exception);

        return $this->createTestResponse($response, $request);
    }

    /**
     * Simulate rendering a web exception.
     */
    protected function renderWebException(Throwable $exception): TestResponse
    {
        $handler = app(Handler::class);
        $request = Request::create('/web/test', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $response = $handler->render($request, $exception);

        return $this->createTestResponse($response, $request);
    }

    public function test_handles_not_found_http_exception()
    {
        $exception = new NotFoundHttpException;

        $response = $this->renderApiException($exception);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'API endpoint not found.',
            ]);
    }

    public function test_handles_model_not_found_exception()
    {
        $exception = new ModelNotFoundException;

        $response = $this->renderApiException($exception);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Resource not found.',
            ]);
    }

    public function test_handles_validation_exception()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];
        $validator = Validator::make($data, $rules);

        $exception = new ValidationException($validator);

        $response = $this->renderApiException($exception);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => ['name' => ['The name field is required.']],
            ]);
    }

    public function test_handles_authentication_exception()
    {
        $exception = new AuthenticationException;

        $response = $this->renderApiException($exception);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_handles_http_exception()
    {
        $exception = new HttpException(403, 'Forbidden');

        $response = $this->renderApiException($exception);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
    }

    public function test_handles_generic_exception()
    {
        $exception = new \Exception('Generic error message');

        $response = $this->renderApiException($exception);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => 'Generic error message',
            ]);
    }

    public function test_handles_not_found_http_exception_for_web()
    {
        $response = $this->renderWebException(new NotFoundHttpException);

        $response->assertStatus(404);
        $response->assertViewIs('errors.404');
    }

    public function test_handles_http_exception_for_web()
    {
        $response = $this->renderWebException(new HttpException(500, 'Internal Server Error'));

        $response->assertStatus(500);
        $response->assertViewIs('errors.500');
    }
}
