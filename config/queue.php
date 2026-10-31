<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
    ],
    'batching' => ['database' => env('DB_CONNECTION', 'sqlite'), 'table' => 'job_batches'],
    'failed' => ['driver' => 'null'],
];
