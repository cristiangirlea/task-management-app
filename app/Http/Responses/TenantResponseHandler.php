<?php

namespace App\Http\Responses;

use App\Handlers\ResponseHandler;

final class TenantResponseHandler extends ResponseHandler
{
    /**
     * Define the name of the resource.
     */
    protected function getResourceName(): string
    {
        return 'tenant';
    }

    /**
     * Fetch dynamic messages specific to the Tenant resource.
     * Overrides the parent implementation to localize messages for this resource.
     */
    protected function fetchMessages(string $resourceName, string $action): array
    {
        return [
            'success' => __("$resourceName.$action.success"),
            'error' => __("$resourceName.$action.error"),
            'not_found_error' => __("$resourceName.$action.not_found_error"),
        ];
    }
}
