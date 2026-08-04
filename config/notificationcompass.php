<?php

declare(strict_types=1);

return [
    'table' => 'notificationcompass_preferences',
    'context_table' => 'notificationcompass_context_preferences',
    'definitions' => [],
    'definition_providers' => [],
    'default' => false,
    'channels' => [],
    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 300,
        'prefix' => 'notificationcompass:preferences',
    ],
];
