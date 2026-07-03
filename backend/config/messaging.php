<?php

declare(strict_types=1);

return [
    'outbox_stream' => env('OUTBOX_STREAM', 'grantgenie.events'),
];
