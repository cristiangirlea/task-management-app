<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseManager
{
    /**
     * Build an API success response.
     *
     * @param string|null $resourceClass
     * @param mixed|null $data
     * @param string|null $message
     * @param int $status
     * @return JsonResponse
     */
    public static function apiSuccess(
        ?string $resourceClass,
                $data = null,
        ?string $message = null,
        int $status = 200
    ): JsonResponse {
        $resource = $resourceClass && $data
            ? ResourceHelper::item($resourceClass, $data)
            : $data;

        return ResponseHelper::success($resource, $message, $status);
    }

    /**
     * Build an API error response.
     *
     * @param string $message
     * @param int $status
     * @param mixed|null $data
     * @return JsonResponse
     */
    public static function apiError(string $message, int $status = 400, $data = null): JsonResponse
    {
        return ResponseHelper::error($message, $status, $data);
    }

    /**
     * Build a view response.
     *
     * @param string $view
     * @param array|null $data
     * @param string|null $flashMessage
     * @param string $flashType
     * @return \Illuminate\Contracts\View\View
     */
    public static function viewResponse(
        string $view,
        ?array $data = [],
        ?string $flashMessage = null,
        string $flashType = 'info'
    ) {
        if ($flashMessage) {
            session()->flash('message', $flashMessage);
            session()->flash('message_type', $flashType);
        }

        return view($view, $data);
    }
}
