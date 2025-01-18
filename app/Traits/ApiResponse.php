<?php

namespace App\Traits;

use App\Helpers\ResponseManager;

trait ApiResponse
{
    protected function respondApiSuccess($resourceClass, $data = null, $message = null, $status = 200)
    {
        return ResponseManager::apiSuccess($resourceClass, $data, $message, $status);
    }

    protected function respondApiError($message, $status = 400, $data = null)
    {
        return ResponseManager::apiError($message, $status, $data);
    }
}
