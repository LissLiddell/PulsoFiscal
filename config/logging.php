<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => ['stderr'], 'ignore_exceptions' => false],
        'stderr' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'level' => env('LOG_LEVEL', 'warning'),
        ],
    ],
];
