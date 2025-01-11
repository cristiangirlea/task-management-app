<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class Handler
{
    public function invalidJson($request, ValidationException $exception): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => 'Validation failed.',
            'errors' => $exception->errors(),
        ], $exception->status);
    }

}
