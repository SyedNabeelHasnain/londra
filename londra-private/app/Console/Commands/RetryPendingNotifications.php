<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Jobs\SendInquiryEmail;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
final class RetryPendingNotifications extends Command
{
    protected $signature='londra:retry-pending-notifications';
    protected $description='Queue notifications that have not previously been queued.';
    public function handle(): int
    {
        if(!app(\App\Services\PublicationState::class)->mailReady()){$this->info('Notification sending is disabled or awaiting SMTP verification.');return self::SUCCESS;}
        $count=0;
        NotificationDelivery::where('status','pending')->whereNull('smtp_accepted_at')->orderBy('id')->chunkById(100,function($records)use(&$count):void{
            foreach($records as $record){
                DB::transaction(function()use($record,&$count):void{
                    $delivery=NotificationDelivery::lockForUpdate()->find($record->id);
                    if(!$delivery||$delivery->status!=='pending'||$delivery->smtp_accepted_at){return;}
                    SendInquiryEmail::dispatch($delivery->id)->onConnection('database')->beforeCommit();
                    $delivery->update(['status'=>'queued','queued_at'=>now()]);$count++;
                });
            }
        });
        $this->info($count.' notification jobs queued.');return self::SUCCESS;
    }
}
