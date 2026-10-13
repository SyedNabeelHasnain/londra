<?php
declare(strict_types=1);
namespace App\Jobs;
use App\Contracts\EmailTransport;
use App\Models\DataRequest;
use App\Models\PrivacyDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
final class SendPrivacyEmail implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 30;
    public function __construct(public readonly int $deliveryId) {}
    public function backoff(): array { return [60, 300, 900]; }
    public function middleware(): array { return [(new WithoutOverlapping('privacy-delivery:'.$this->deliveryId))->releaseAfter(60)->expireAfter(120)]; }
    public function handle(EmailTransport $transport): void
    {
        $candidate = PrivacyDelivery::find($this->deliveryId);
        if (!$candidate) { return; }
        try {
            DB::transaction(function () use ($candidate, $transport): void {
                $request = DataRequest::lockForUpdate()->find($candidate->data_request_id);
                $delivery = PrivacyDelivery::lockForUpdate()->find($candidate->id);
                if (!$request || !$delivery || $delivery->smtp_accepted_at || $delivery->status === 'cancelled') { return; }
                if (!app(\App\Services\PublicationState::class)->mailReady()) { $delivery->update(['status' => 'pending', 'queued_at' => null]); return; }
                if (!in_array($delivery->kind, ['verify', 'admin', 'complete', 'decision'], true)) { throw new \RuntimeException('Unsupported privacy notification category.'); }
                if (!in_array($request->locale, ['en', 'it', 'es'], true)) { throw new \RuntimeException('Unsupported privacy notification locale.'); }
                if ($delivery->kind === 'verify' && ($request->status !== 'received' || $request->verified_at || !$request->verification_token || !$request->verification_expires_at || $request->verification_expires_at->lte(now()))) { $delivery->update(['status' => 'cancelled']); return; }
                if ($delivery->kind === 'admin' && (!$request->verified_at || $request->status !== 'in_review')) { $delivery->update(['status' => 'cancelled']); return; }
                if (($delivery->kind === 'complete' && ($request->status !== 'completed' || !$request->completed_at)) || ($delivery->kind === 'decision' && ($request->status !== 'refused' || !$request->completed_at))) { $delivery->update(['status' => 'cancelled']); return; }
                $locale = $delivery->kind === 'admin' ? 'en' : $request->locale;
                $recipient = $delivery->kind === 'admin' ? (string) config('londra.contact.email') : (string) $request->email;
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { throw new \RuntimeException('Privacy notification recipient is invalid.'); }
                app(\App\Services\PublicCopy::class)->applyLocale($locale);
                $contact = app(\App\Services\PublicContact::class)->current();
                $text = (string) __('ui.privacy_email_'.$delivery->kind, [], $locale);
                $url = $delivery->kind === 'verify' ? rtrim(config('app.url'), '/').'/'.$request->locale.'/privacy-request/verify/'.$request->verification_token : null;
                if ($delivery->kind === 'admin') { $url = rtrim(config('app.url'), '/').'/admin/data-requests/'.$request->id.'/edit'; }
                if ($delivery->kind === 'decision') { $text .= "\n\n".$request->decision_reason."\n\n".__('ui.privacy_decision_rights', [], $locale); }
                $subject = (string) __('ui.privacy_email_subject', [], $locale).' | '.$request->reference;
                $plain = $text."\n\n".__('ui.reference', [], $locale).': '.$request->reference.($url ? "\n\n".$url : '')."\n\nLondra Transfers\n".$contact['phone_display']."\n".$contact['email'];
                $html = view('emails.privacy-request', ['text' => $text, 'url' => $url, 'reference' => $request->reference, 'locale' => $locale, 'contact' => $contact])->render();
                $transport->send($recipient, $subject, $html, $plain);
                $delivery->update(['status' => 'smtp_accepted', 'smtp_accepted_at' => now(), 'last_error_class' => null, 'attempts' => $delivery->attempts + 1]);
                if (in_array($delivery->kind, ['complete', 'decision'], true)) { $request->scrubContact(); }
            }, 1);
        } catch (\Throwable $exception) {
            PrivacyDelivery::whereKey($this->deliveryId)->whereNull('smtp_accepted_at')->update(['status' => 'retrying', 'attempts' => DB::raw('attempts + 1'), 'last_error_class' => get_class($exception)]);
            throw new \RuntimeException('Privacy notification transport failed. Delivery record '.$this->deliveryId.'.');
        }
    }
    public function failed(?\Throwable $exception): void { PrivacyDelivery::whereKey($this->deliveryId)->whereNull('smtp_accepted_at')->update(['status' => 'failed']); }
}
