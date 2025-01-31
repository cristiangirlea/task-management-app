<?php

return [
    // Default messages for undefined resources/actions
    'default' => [
        'store' => [
            'success' => ':Resource created successfully.',
            'validation_error' => ':Resource creation failed due to validation errors.',
            'error' => 'Failed to create :resource.',
        ],
        'update' => [
            'success' => ':Resource updated successfully.',
            'validation_error' => ':Resource update failed due to validation errors.',
            'error' => 'Failed to update :resource.',
        ],
        'destroy' => [
            'success' => ':Resource deleted successfully.',
            'not_found_error' => 'The :resource you want to delete does not exist.',
            'error' => 'Failed to delete :resource.',
        ],
        'show' => [
            'success' => ':Resource retrieved successfully.',
            'not_found_error' => ':Resource not found.',
        ],
        'index' => [
            'success' => ':Resource list retrieved successfully.',
            'error' => 'Failed to retrieve :resource list.',
        ],
        'http_error' => 'An HTTP error occurred while processing the request.',
        'general_error' => 'An error occurred, please try again.',
    ]
];
