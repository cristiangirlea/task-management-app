<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseHelper
{
    /**
     * Success response with data and optional message.
     *
     * @param  mixed  $data
     * @param  string|null  $message
     * @param  int  $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'status' => 'success',
        ];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data) {
            $response['data'] = $data instanceof JsonResource ? $data->resolve() : $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Error response with a message and optional data.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @param  mixed|null  $data
     * @return JsonResponse
     */
    public static function error(string $message, int $statusCode = 400, $data = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }
}
