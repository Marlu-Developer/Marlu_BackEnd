<?php

// Prefer CORS_ALLOWED_ORIGINS; otherwise FRONTEND_URL; otherwise local dev defaults.
// Empty allowed_origins means Laravel sends no Access-Control-Allow-Origin → browser CORS failure.
$rawOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', ''));
if ($rawOrigins === '') {
    $rawOrigins = trim((string) env('FRONTEND_URL', ''));
}
if ($rawOrigins === '' && env('APP_ENV') !== 'production') {
    $rawOrigins = 'http://localhost:8083,http://127.0.0.1:8083';
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $rawOrigins)))),

    'allowed_origins_patterns' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))))),

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'X-Request-Id', 'Idempotency-Key'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 60 * 60,

    'supports_credentials' => false,

];
