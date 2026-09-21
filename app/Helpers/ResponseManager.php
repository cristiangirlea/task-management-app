<?php

namespace App\Helpers;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ResponseManager
{
    /**
     * Build an API success response, wrapping $data in $resourceClass when given.
     * Collections and paginators are wrapped as resource collections, anything
     * else as a single resource.
     */
    public static function apiSuccess(
        ?string $resourceClass,
        mixed $data = null,
        ?string $message = null,
        int $status = 200
    ): JsonResponse {
        if ($resourceClass !== null && $data !== null) {
            $data = ($data instanceof Collection || $data instanceof Paginator)
                ? ResourceHelper::collection($resourceClass, $data)
                : ResourceHelper::item($resourceClass, $data);
        }

        return ResponseHelper::success($data, $message, $status);
    }

    public static function apiError(string $message, int $status = 400, mixed $data = null): JsonResponse
    {
        return ResponseHelper::error($message, $status, $data);
    }

    /**
     * Build a view response with an optional flash message.
     *
     * @return View
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
