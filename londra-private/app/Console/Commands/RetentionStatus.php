<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\Inquiry;
use App\Services\InquiryErasure;
use App\Services\PrivacyConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
final class RetentionStatus extends Command
{
    protected $signature = 'londra:retention {--dry-run}';
    protected $description = 'Remove expired unconverted enquiries under the approved retention rule.';
    public function handle(PrivacyConfiguration $configuration, InquiryErasure $erasure): int
    {
        $v = $configuration->values();
        $days = filter_var($v['retention_days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        if (!$days || !$configuration->retentionEnabled()) { $this->info('Retention is awaiting administrator approval. No records changed.'); return self::SUCCESS; }
        $cutoff = now()->subDays($days);
        $query = Inquiry::whereNull('converted_at')->whereNull('legal_hold_at')->where('last_substantive_contact_at', '<=', $cutoff)->where(fn($q) => $q->whereNull('active_until')->orWhere('active_until', '<=', now()));
        if ($this->option('dry-run')) { $this->info($query->count().' enquiries eligible. No records changed.'); return self::SUCCESS; }
        $count = 0;
        $query->select('id')->chunkById(50, function ($records) use ($cutoff, $erasure, &$count): void {
            foreach ($records as $record) {
                DB::transaction(function () use ($record, $cutoff, $erasure, &$count): void {
                    $inquiry = Inquiry::lockForUpdate()->find($record->id);
                    if (!$inquiry || $inquiry->converted_at || $inquiry->legal_hold_at || !$inquiry->last_substantive_contact_at || $inquiry->last_substantive_contact_at->gt($cutoff) || ($inquiry->active_until && $inquiry->active_until->isFuture())) { return; }
                    $erasure->erase($inquiry, 'retention'); $count++;
                }, 3);
            }
        });
        $this->info($count.' expired enquiries removed. Follow the recorded Zoho mailbox, export and backup process.');
        return self::SUCCESS;
    }
}
