<?php
return [
    'default' => 'private',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true, 'serve' => false],
        'private' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true, 'serve' => false],
        'media' => ['driver' => 'local', 'root' => storage_path('app/public/media'), 'url' => rtrim(env('APP_URL', ''), '/').'/media', 'visibility' => 'public', 'throw' => true],
    ],
    'links' => [],
];
