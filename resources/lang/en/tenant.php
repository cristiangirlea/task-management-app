<?php

return [
    'store' => [
        'validation' => [
            'required_fields' => 'All required fields must be provided to create a tenant.',
            'unique_error' => 'The tenant name must be unique.',
            'invalid_format' => 'One or more fields have an invalid format.',
        ],
        'success' => 'Tenant has been successfully created.',
        'error' => 'Failed to create a tenant.',
    ],
    'update' => [
        'validation' => [
            'required_fields' => 'All required fields must be provided to update the tenant.',
            'unique_error' => 'The updated tenant name must be unique.',
            'missing_id' => 'The tenant ID is required for updating the tenant.',
        ],
        'success' => 'Tenant has been successfully updated.',
        'error' => 'Failed to update the tenant.',
    ],
    'destroy' => [
        'validation' => [
            'missing_id' => 'The tenant ID must be provided to delete the tenant.',
        ],
        'success' => 'Tenant has been successfully deleted.',
        'error' => 'Failed to delete the tenant.',
    ],
    'not_found' => [
        'single' => 'Tenant not found.',
        'multiple' => 'No tenants found.',
    ],
];
