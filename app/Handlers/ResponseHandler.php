<?php

namespace App\Handlers;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

abstract class ResponseHandler
{
    /**
     * Handle response generation and error handling.
     *
     * @param callable $callback
     * @param string|null $resourceName
     * @param string|null $action
     * @return \Illuminate\Http\JsonResponse
     */
    final public function handle(
        callable $callback,
        ?string $resourceName = null,
        ?string $action = null
    ): \Illuminate\Http\JsonResponse {
        $resourceName = $resourceName ?? $this->getResourceName();
        $action = $action ?? $this->getActionName();

        // Combine default, config, and handler-specific messages
        $messages = array_replace_recursive(
            $this->defaultMessages($resourceName),     // Default fallback messages
            $this->configMessages($resourceName, $action), // Messages from response_messages.php
            $this->fetchMessages($resourceName, $action)   // Custom resource-specific messages
        );

        try {
            // Execute callback
            $data = $callback();

            // Success Response
            return $this->successResponse(
                $data,
                $this->replacePlaceholders($messages['success'], $resourceName),
                $messages['success_status'] ?? 200
            );
        } catch (ValidationException $e) {
            // Validation Error Response
            return $this->errorResponse(
                $this->replacePlaceholders($messages['validation_error'] ?? 'Validation failed.', $resourceName),
                422,
                $e->errors()
            );
        } catch (ModelNotFoundException $e) {
            // Not Found Error Response
            return $this->errorResponse(
                $this->replacePlaceholders(
                    $messages['not_found_error'] ?? 'Resource not found.',
                    $resourceName
                ),
                404
            );
        } catch (HttpException $e) {
            // HTTP Error Response
            return $this->errorResponse(
                $messages['http_error'] ?? $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (Exception $e) {
            // General Error Response
            return $this->errorResponse(
                $this->replacePlaceholders(
                    $messages['error'] ?? 'Something went wrong.',
                    $resourceName
                ),
                $messages['error_status'] ?? 500,
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Define default fallback messages for all resources.
     *
     * @param string $resourceName
     * @return array
     */
    protected function defaultMessages(string $resourceName): array
    {
        return [
            'success' => ':Resource action completed successfully.',
            'validation_error' => 'Validation failed for the :Resource resource.',
            'error' => 'An error occurred while processing :Resource.',
            'not_found_error' => ':Resource not found.',
            'http_error' => 'An HTTP error occurred.',
        ];
    }

    /**
     * Fetch messages from the response_messages.php config file.
     *
     * @param string $resourceName
     * @param string $action
     * @return array
     */
    protected function configMessages(string $resourceName, string $action): array
    {
        $config = config('response_messages'); // Load the response_messages.php config

        // Global default messages for the action
        $defaultMessages = $config['default'][$action] ?? [];

        // Resource-specific messages override the defaults (if defined)
        $resourceMessages = $config[$resourceName][$action] ?? [];

        // Merge resource-specific messages with defaults
        return array_merge($defaultMessages, $resourceMessages);
    }

    /**
     * Resource-specific handlers can override this to supply custom messages.
     *
     * @param string $resourceName
     * @param string $action
     * @return array
     */
    protected function fetchMessages(string $resourceName, string $action): array
    {
        // Allow handlers to override messages if necessary
        return [];
    }

    /**
     * Force resource-specific handlers to define the resource name.
     */
    abstract protected function getResourceName(): string;

    /**
     * Let handlers override the action name if needed.
     */
    protected function getActionName(): string
    {
        return debug_backtrace()[2]['function'] ?? 'unknown_action';
    }

    /**
     * Replace placeholders in messages.
     */
    protected function replacePlaceholders(?string $message, string $resourceName): string
    {
        return str_replace(':Resource', $resourceName, $message ?? '');
    }

    /**
     * Create a success response.
     */
    protected function successResponse(
        $data,
        string $message = 'Successfully processed.',
        int $status = 200
    ): \Illuminate\Http\JsonResponse {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Create an error response.
     */
    protected function errorResponse(
        string $message,
        int $status,
               $errors = null
    ): \Illuminate\Http\JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
