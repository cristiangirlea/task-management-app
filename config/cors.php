<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // The MCP endpoint and OAuth's are called from browsers too (web-based
    // MCP clients), which must also read the 401's WWW-Authenticate header.
    'paths' => ['api/*', 'mcp', 'oauth/*', '.well-known/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['WWW-Authenticate', 'Mcp-Session-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];
