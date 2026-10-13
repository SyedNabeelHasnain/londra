<?php
declare(strict_types=1);
namespace App\Jobs;
use App\Services\InquirySmtpSender;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;
final class SendInquiryEmail implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 30;
    public function __construct(public readonly int $deliveryId) {}
    public function backoff(): array { return [60, 300, 900]; }
    public function middleware(): array { return [(new WithoutOverlapping('inquiry-delivery:'.$this->deliveryId))->releaseAfter(60)->expireAfter(120)]; }
    public function handle(InquirySmtpSender $sender): void
    {
        $candidate = NotificationDelivery::find($this->deliveryId);
        if (!$candidate) { return; }
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($candidate, $sender): void {
                $inquiry = \App\Models\Inquiry::lockForUpdate()->find($candidate->inquiry_id);
                $delivery = NotificationDelivery::lockForUpdate()->find($candidate->id);
                if (!$delivery || !$inquiry || $delivery->smtp_accepted_at) { return; }
                if (!app(\App\Services\PublicationState::class)->mailReady()) { $delivery->update(['status' => 'pending', 'queued_at' => null]); return; }
                $sender->send($inquiry, $delivery->kind);
                $delivery->update(['status' => 'smtp_accepted', 'smtp_accepted_at' => now(), 'last_error_class' => null, 'attempts' => $delivery->attempts + 1]);
            }, 1);
        } catch (Throwable $exception) {
            NotificationDelivery::whereKey($this->deliveryId)->whereNull('smtp_accepted_at')->update(['status' => 'retrying', 'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'), 'last_error_class' => get_class($exception)]);
            throw new \RuntimeException('Notification transport failed. Delivery record '.$this->deliveryId.'.');
        }
    }
    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->whereNull('smtp_accepted_at')->update(['status' => 'failed']);
    }
}
