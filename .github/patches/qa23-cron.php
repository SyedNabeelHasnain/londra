<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$autoload = __DIR__.'/vendor/autoload.php';
$bootstrap = __DIR__.'/bootstrap/app.php';

if (!is_file($autoload) || !is_file($bootstrap)) {
    fwrite(STDERR, "Application dependencies are unavailable.\n");
    exit(1);
}

require $autoload;
$app = require $bootstrap;

try {
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $result = $kernel->call('schedule:run', ['--no-interaction' => true]);
    exit($result);
} catch (\Throwable $exception) {
    fwrite(STDERR, "Scheduled application tasks failed. Review the private Laravel log.\n");
    exit(1);
}
