<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authorized Email
    |--------------------------------------------------------------------------
    |
    | Only this email address is allowed to access the application.
    | Set this in your .env file as AUTHORIZED_EMAIL
    |
    */
    'authorized_email' => env('AUTHORIZED_EMAIL', 'putrafyp@gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different endpoints
    |
    */
    'rate_limits' => [
        'auth' => env('RATE_LIMIT_AUTH', '5,1'), // 5 requests per minute for auth endpoints
        'api' => env('RATE_LIMIT_API', '60,1'), // 60 requests per minute for API endpoints
        'ai' => env('RATE_LIMIT_AI', '20,1'), // 20 requests per minute for AI endpoints
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation Limits
    |--------------------------------------------------------------------------
    |
    | Maximum lengths for input fields to prevent DoS attacks
    |
    */
    'max_lengths' => [
        'title' => 500,
        'description' => 10000,
        'body' => 50000,
        'notes' => 10000,
        'label' => 255,
        'username' => 255,
        'password' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Limits
    |--------------------------------------------------------------------------
    |
    | Maximum file size and allowed types for imports
    |
    */
    'file_upload' => [
        'max_size' => env('MAX_UPLOAD_SIZE', 10485760), // 10MB in bytes
        'allowed_mime_types' => ['application/json', 'text/json'],
    ],
];

