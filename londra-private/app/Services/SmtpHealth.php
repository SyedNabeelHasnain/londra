<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Setting;
final class SmtpHealth
{
    public function fingerprint(): string
    {
        return hash_hmac('sha256', json_encode(['smtp' => config('mail.mailers.smtp'), 'from' => config('mail.from'), 'reply' => config('mail.reply_to')], JSON_THROW_ON_ERROR), config('app.key'));
    }
    public function verified(): bool
    {
        $health = Setting::find('smtp-health')?->value ?? [];
        return isset($health['configuration_fingerprint'], $health['smtp_accepted_at']) && hash_equals($this->fingerprint(), (string) $health['configuration_fingerprint']);
    }
}
