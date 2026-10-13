<?php
declare(strict_types=1);
namespace App\Actions;
use App\Jobs\SendInquiryEmail;
use App\Models\Inquiry;
use App\Models\NotificationDelivery;
use App\Models\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class SubmitInquiry
{
    public function handle(array $data, string $locale, string $sessionId): Inquiry
    {
        $key = hash_hmac('sha256', $sessionId.':'.$data['submission_id'], config('app.key'));
        $fingerprint = hash_hmac('sha256', json_encode(array_intersect_key($data, array_flip(['name', 'email', 'phone', 'type', 'page_key', 'service_key'])), JSON_THROW_ON_ERROR).':'.$locale, config('app.key'));
        $existing = Inquiry::where('submission_key', $key)->first();
        if ($existing) { return $this->existing($existing, $fingerprint); }
        try {
            return DB::transaction(function () use ($data, $locale, $key, $fingerprint): Inquiry {
                $privacy = Page::with('translations')->where('key', 'privacy')->where('published', true)->firstOrFail();
                $notice = $privacy->translation($locale);
                abort_unless($notice && $notice->ready, 503);
                $inquiry = Inquiry::create([
                    'reference' => 'LT-'.strtoupper((string) Str::ulid()), 'submission_key' => $key,
                    'payload_fingerprint' => $fingerprint, 'type' => $data['type'], 'locale' => $locale, 'status' => 'new',
                    'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                    'email_lookup' => hash_hmac('sha256', mb_strtolower($data['email']), config('app.key')),
                    'subject' => null, 'message' => null, 'last_substantive_contact_at' => now(),
                    'page_key' => $data['page_key'] ?? null, 'service_key' => $data['service_key'] ?? null,
                    'privacy_notice_at' => now(), 'privacy_notice_version' => $notice->id.':'.$notice->updated_at->toIso8601String(),
                ]);
                foreach (['admin', 'customer'] as $kind) {
                    $delivery = NotificationDelivery::create(['inquiry_id' => $inquiry->id, 'kind' => $kind, 'status' => 'pending']);
                    if (app(\App\Services\PublicationState::class)->mailReady()) {
                        SendInquiryEmail::dispatch($delivery->id)->onConnection('database')->beforeCommit();
                        $delivery->update(['status' => 'queued', 'queued_at' => now()]);
                    }
                }
                return $inquiry;
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                $existing = Inquiry::where('submission_key', $key)->first();
                if ($existing) { return $this->existing($existing, $fingerprint); }
            }
            throw $exception;
        }
    }
    private function existing(Inquiry $inquiry, string $fingerprint): Inquiry
    {
        if (!hash_equals($inquiry->payload_fingerprint, $fingerprint)) { throw ValidationException::withMessages(['submission_id' => __('ui.enquiry_form_used')]); }
        return $inquiry;
    }
}
