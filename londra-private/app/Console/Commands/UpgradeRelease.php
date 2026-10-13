<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Services\ReleaseUpdater;
use Illuminate\Console\Command;
final class UpgradeRelease extends Command
{
    protected $signature = 'londra:upgrade';
    protected $description = 'Apply the current release without changing private configuration or existing customer records.';
    public function handle(ReleaseUpdater $updater): int { $updater->run(); $this->info('Release '.ReleaseUpdater::VERSION.' applied.'); return self::SUCCESS; }
}
