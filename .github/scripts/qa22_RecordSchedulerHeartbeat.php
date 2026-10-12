<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\Setting;
use Illuminate\Console\Command;
final class RecordSchedulerHeartbeat extends Command
{
    protected $signature='londra:scheduler-heartbeat';
    protected $description='Record a successful scheduled application check without storing customer data.';
    public function handle():int
    {
        Setting::updateOrCreate(['key'=>'scheduler-heartbeat'],['value'=>['last_ran_at'=>now()->toIso8601String()]]);
        $this->info('Scheduler heartbeat recorded.');
        return self::SUCCESS;
    }
}
