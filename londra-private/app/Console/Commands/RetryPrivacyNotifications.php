<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Jobs\SendPrivacyEmail;
use App\Models\PrivacyDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
final class RetryPrivacyNotifications extends Command
{
    protected $signature = 'londra:retry-privacy-notifications';
    protected $description = 'Queue pending privacy-request notifications.';
    public function handle(): int
    {
        if (!app(\App\Services\PublicationState::class)->mailReady() || !Schema::hasTable('privacy_deliveries')) { return self::SUCCESS; }
        PrivacyDelivery::where('status', 'pending')->select('id')->chunkById(50, function ($records): void {
            foreach ($records as $record) {
                DB::transaction(function () use ($record): void {
                    $delivery = PrivacyDelivery::lockForUpdate()->find($record->id);
                    if (!$delivery || $delivery->status !== 'pending') { return; }
                    SendPrivacyEmail::dispatch($delivery->id)->onConnection('database')->beforeCommit();
                    $delivery->update(['status' => 'queued', 'queued_at' => now()]);
                }, 3);
            }
        });
        return self::SUCCESS;
    }
}
