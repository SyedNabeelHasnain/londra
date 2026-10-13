<?php
return [
    'default' => env('QUEUE_CONNECTION', 'database'),
    'connections' => [
        'database' => ['driver' => 'database', 'connection' => 'mysql', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 180, 'after_commit' => false],
    ],
    'failed' => ['driver' => 'database-uuids', 'database' => 'mysql', 'table' => 'failed_jobs'],
];
