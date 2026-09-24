<?php

return [
    'name' => env('APP_NAME', 'ECFHL'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'America/Moncton',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_CA',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
];
