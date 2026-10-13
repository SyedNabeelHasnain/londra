<?php
declare(strict_types=1);
namespace Tests;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app=require __DIR__.'/../bootstrap/app.php';
        $app->booting(static function(Application $guardedApp): void {
            $configuration=$guardedApp['config'];
            $name=(string)$configuration->get('database.connections.mysql.database');
            if(!$guardedApp->environment('testing')||$configuration->get('database.default')!=='mysql'||!str_ends_with($name,'_test')){
                throw new \RuntimeException('Use an isolated MySQL database ending in _test. Tests must never run against production.');
            }
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }
}
