<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('mcp/*')) {
            return $this->handleApiException($exception);
        }

        return $this->handleWebException($exception);
    }

    protected function handleApiException(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'status' => 'error',
                'message' => 'API endpoint not found.',
            ], 404);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($exception instanceof HttpException) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        $payload = [
            'status' => 'error',
            'message' => 'An unexpected error occurred.',
        ];

        if (config('app.debug')) {
            $payload['error'] = $exception->getMessage();
        }

        return response()->json($payload, 500);
    }

    protected function handleWebException(Throwable $exception): Response
    {
        if ($exception instanceof NotFoundHttpException) {
            return response()->view('errors.404', [], 404);
        }

        if ($exception instanceof HttpException) {
            return response()->view('errors.'.$exception->getStatusCode(), ['exception' => $exception], $exception->getStatusCode());
        }

        return response()->view('errors.500', ['exception' => $exception], 500);
    }
}
