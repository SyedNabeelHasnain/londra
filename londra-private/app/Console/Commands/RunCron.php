<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
final class RunCron extends Command
{
    protected $signature = 'londra:cron';
    protected $description = 'Process bounded mail and maintenance work in one PHP process for shared hosting.';
    public function handle(): int
    {
        $lock = Cache::lock('londra-direct-cron', 180);
        if (!$lock->get()) { $this->info('Another cron run is active.'); return self::SUCCESS; }
        try {
            foreach (['londra:retry-pending-notifications', 'londra:retry-privacy-notifications'] as $command) { if (Artisan::call($command) !== 0) { return self::FAILURE; } }
            $london = now()->timezone('Europe/London');
            $dailyKey = 'londra-maintenance:'.$london->format('Y-m-d');
            if ($london->format('H:i') >= '03:20' && Cache::add($dailyKey, true, 172800)) {
                if (Artisan::call('londra:retention') !== 0 || Artisan::call('queue:prune-failed', ['--hours' => 168]) !== 0) { Cache::forget($dailyKey); return self::FAILURE; }
            }
            if (Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--max-jobs' => 20, '--max-time' => 40, '--tries' => 3, '--timeout' => 30]) !== 0) { return self::FAILURE; }
            Setting::updateOrCreate(['key' => 'cron-health'], ['value' => ['last_success_at' => now()->toIso8601String(), 'php_version' => PHP_VERSION]]);
            $this->info('Londra background work completed.'); return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
