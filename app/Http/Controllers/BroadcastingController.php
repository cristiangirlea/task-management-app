<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class BroadcastingController extends ApiBaseController
{
    /**
     * What the web app needs to listen for live board updates, read at run
     * time so one web image serves every deployment: Reverb's public app key,
     * and the WebSocket address when it is not the site itself. Null when
     * live updates are off.
     */
    public function config(): JsonResponse
    {
        $settings = config('broadcasting.default') === 'reverb'
            ? [
                'key' => config('broadcasting.connections.reverb.key'),
                'url' => config('broadcasting.connections.reverb.public_url'),
            ]
            : null;

        return $this->respondApiSuccess(null, $settings, 'Broadcasting settings retrieved successfully');
    }
}
