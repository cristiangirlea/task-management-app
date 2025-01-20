<?php

namespace App\Handlers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class ResponseHandler
{
    public function handle(
        callable $callback,
        ?string $resourceName = null,
        ?string $action = null
    ): \Illuminate\Http\JsonResponse {
        $resourceName = $resourceName ?? $this->extractResourceName();
        $action = $action ?? $this->extractActionName();

        $messages = $this->fetchMessages($resourceName, $action);

        try {
            $data = $callback();

            $successMessage = $this->replacePlaceholders($messages['success'], $resourceName);
            $successStatus = $messages['success_status'] ?? 200;

            return $this->successResponse($data, $successMessage, $successStatus);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                $this->replacePlaceholders($messages['validation_error'], $resourceName),
                422,
                $e->errors()
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(
                $this->replacePlaceholders($messages['not_found_error'], $resourceName),
                404
            );
        } catch (HttpException $e) {
            return $this->errorResponse(
                $messages['http_error'] ?? $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (Exception $e) {
            $errorMessage = $this->replacePlaceholders($messages['error'], $resourceName);
            $errorStatus = $messages['error_status'] ?? 500;

            return $this->errorResponse($errorMessage, $errorStatus, ['exception' => $e->getMessage()]);
        }
    }

    private function fetchMessages(string $resourceName, string $action): array
    {
        $config = config('response_messages');

        // Look for specific resource/action, fallback to default if not found
        return $config[$resourceName][$action] ?? $config['default'][$action] ?? [];
    }

    private function replacePlaceholders(?string $message, string $resourceName): string
    {
        return str_replace(':Resource', $resourceName, $message ?? '');
    }

    private function successResponse($data, string $message, int $status): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function errorResponse(string $message, int $status, $errors = null): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    private function extractResourceName(): string
    {
        $controller = class_basename(debug_backtrace()[3]['class'] ?? 'UnknownController');
        return str_replace('Controller', '', $controller);
    }

    private function extractActionName(): string
    {
        return debug_backtrace()[3]['function'] ?? 'unknown_action';
    }
}
