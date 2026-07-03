<?php

declare(strict_types=1);

return [
    'base_url' => env('AI_SERVICE_URL', 'http://ai-service:8001'),
    'internal_token' => env('AI_SERVICE_INTERNAL_TOKEN', ''),
    'timeout_seconds' => env('AI_SERVICE_TIMEOUT', 30),
    'connect_timeout_seconds' => env('AI_SERVICE_CONNECT_TIMEOUT', 5),
];
