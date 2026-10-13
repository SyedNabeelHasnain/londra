<?php
declare(strict_types=1);
namespace App\Models;
use App\Domain\InquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class Inquiry extends Model
{
    protected $fillable = ['reference', 'submission_key', 'payload_fingerprint', 'type', 'locale', 'status', 'name', 'email', 'phone', 'email_lookup', 'subject', 'message', 'page_key', 'service_key', 'follow_up_at', 'closed_at', 'privacy_notice_at', 'privacy_notice_version', 'last_substantive_contact_at', 'converted_at', 'legal_hold_at', 'legal_hold_reason', 'active_until', 'active_reason'];
    protected $hidden = ['name', 'email', 'phone', 'subject', 'message', 'email_lookup', 'submission_key', 'payload_fingerprint', 'legal_hold_reason', 'active_reason'];
    protected function casts(): array
    {
        return ['name' => 'encrypted', 'email' => 'encrypted', 'phone' => 'encrypted', 'subject' => 'encrypted', 'message' => 'encrypted', 'legal_hold_reason' => 'encrypted', 'active_reason' => 'encrypted', 'active_until' => 'datetime', 'status' => InquiryStatus::class, 'follow_up_at' => 'datetime', 'closed_at' => 'datetime', 'privacy_notice_at' => 'datetime', 'last_substantive_contact_at' => 'datetime', 'converted_at' => 'datetime', 'legal_hold_at' => 'datetime'];
    }
    public function notes(): HasMany { return $this->hasMany(InquiryNote::class); }
    public function deliveries(): HasMany { return $this->hasMany(NotificationDelivery::class); }
    protected static function booted(): void
    {
        static::saving(function (Inquiry $inquiry): void {
            if (!$inquiry->exists && !$inquiry->last_substantive_contact_at) { $inquiry->last_substantive_contact_at = now(); }
            if ($inquiry->active_until && !trim((string) $inquiry->active_reason)) { throw \Illuminate\Validation\ValidationException::withMessages(['active_reason' => 'Record why this enquiry remains active.']); }
            if ($inquiry->legal_hold_at && !trim((string) $inquiry->legal_hold_reason)) { throw \Illuminate\Validation\ValidationException::withMessages(['legal_hold_reason' => 'Record a specific reason for the legal hold.']); }
            if ($inquiry->isDirty('status')) { $inquiry->closed_at = $inquiry->status === InquiryStatus::Closed ? now() : null; }
        });
    }
}
