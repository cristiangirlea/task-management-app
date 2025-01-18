<?php

namespace App\Traits;

use App\Helpers\ResponseManager;

trait ViewResponse
{
    protected function respondView($view, $data = [], $flashMessage = null, $flashType = 'info')
    {
        return ResponseManager::viewResponse($view, $data, $flashMessage, $flashType);
    }
}
