<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseHelper
{
    /**
     * Success envelope: { status: "success", message?: string, data?: mixed }.
     */
    public static function success(mixed $data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'status' => 'success',
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data instanceof JsonResource ? $data->resolve() : $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Error envelope: { status: "error", message: string, data?: mixed }.
     */
    public static function error(string $message, int $statusCode = 400, mixed $data = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }
}
