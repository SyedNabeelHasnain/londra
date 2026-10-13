<?php
declare(strict_types=1);
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
if (file_exists($maintenance = __DIR__.'/../londra-private/storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../londra-private/vendor/autoload.php';
$app = require_once __DIR__.'/../londra-private/bootstrap/app.php';
$app->handleRequest(Request::capture());
