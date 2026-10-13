<?php
declare(strict_types=1);
namespace App\Actions;
use App\Jobs\SendPrivacyEmail;
use App\Models\DataRequest;
use App\Models\PrivacyDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class SubmitDataRequest
{
    public function handle(array $data, string $locale, string $sessionId): DataRequest
    {
        $email = mb_strtolower(trim($data['email']));
        $key = hash_hmac('sha256', 'privacy:'.$sessionId.':'.$data['submission_id'], config('app.key'));
        $fingerprint = hash_hmac('sha256', $locale.':'.$email, config('app.key'));
        $existing = DataRequest::where('submission_key', $key)->first();
        if ($existing) { return $this->existing($existing, $fingerprint); }
        try {
            return DB::transaction(function () use ($email, $locale, $key, $fingerprint): DataRequest {
                $token = bin2hex(random_bytes(32));
                $request = DataRequest::create([
                    'reference' => 'LT-DP-'.strtoupper((string) Str::ulid()), 'submission_key' => $key, 'payload_fingerprint' => $fingerprint,
                    'email' => $email, 'email_lookup' => hash_hmac('sha256', $email, config('app.key')), 'locale' => $locale,
                    'status' => 'received', 'verification_token' => $token, 'verification_hash' => hash('sha256', $token),
                    'inquiry_cutoff_id' => (int) \App\Models\Inquiry::where('email_lookup', hash_hmac('sha256', $email, config('app.key')))->max('id'),
                    'verification_expires_at' => now()->addHours(48), 'due_at' => now()->addMonthNoOverflow(),
                ]);
                $this->queue($request, 'verify');
                return $request;
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                $existing = DataRequest::where('submission_key', $key)->first();
                if ($existing) { return $this->existing($existing, $fingerprint); }
            }
            throw $exception;
        }
    }
    public function queue(DataRequest $request, string $kind): PrivacyDelivery
    {
        $delivery = PrivacyDelivery::firstOrCreate(['data_request_id' => $request->id, 'kind' => $kind], ['status' => 'pending']);
        if (app(\App\Services\PublicationState::class)->mailReady() && $delivery->status === 'pending') {
            SendPrivacyEmail::dispatch($delivery->id)->onConnection('database')->beforeCommit();
            $delivery->update(['status' => 'queued', 'queued_at' => now()]);
        }
        return $delivery;
    }
    private function existing(DataRequest $request, string $fingerprint): DataRequest
    {
        if (!hash_equals($request->payload_fingerprint, $fingerprint)) { throw ValidationException::withMessages(['email' => __('ui.privacy_try_again')]); }
        return $request;
    }
}
