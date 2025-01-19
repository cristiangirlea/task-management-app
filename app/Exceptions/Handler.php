<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Throwable;

use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            Log::info('API exception rendering started');
            return $this->handleApiException($exception);
        }

        return $this->handleWebException($exception);
    }

    protected function handleApiException(Throwable $exception): \Illuminate\Http\JsonResponse
    {
        Log::info('Handling API exception: ' . get_class($exception));

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

        return response()->json([
            'status' => 'error',
            'message' => 'An unexpected error occurred.',
            'error' => $exception->getMessage(),
        ], 500);
    }

    protected function handleWebException(Throwable $exception): \Symfony\Component\HttpFoundation\Response
    {
        Log::info('Handling Web exception: ' . get_class($exception));

        if ($exception instanceof NotFoundHttpException) {
            return response()->view('errors.404', [], 404);
        }

        if ($exception instanceof HttpException) {
            return response()->view('errors.' . $exception->getStatusCode(), ['exception' => $exception], $exception->getStatusCode());
        }

        return response()->view('errors.500', ['exception' => $exception], 500);
    }
}
