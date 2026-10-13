<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class DataRequest extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['email', 'email_lookup', 'submission_key', 'payload_fingerprint', 'verification_token', 'verification_hash', 'decision_reason', 'inquiry_cutoff_id'];
    protected function casts(): array
    {
        return ['email' => 'encrypted', 'verification_token' => 'encrypted', 'decision_reason' => 'encrypted', 'verification_expires_at' => 'datetime', 'verified_at' => 'datetime', 'due_at' => 'datetime', 'database_erased_at' => 'datetime', 'completed_at' => 'datetime', 'erased_count' => 'integer'];
    }
    public function deliveries(): HasMany { return $this->hasMany(PrivacyDelivery::class); }
    public function scrubContact(): void
    {
        $this->update(['email' => null, 'email_lookup' => null, 'verification_token' => null, 'verification_hash' => null, 'decision_reason' => null, 'submission_key' => bin2hex(random_bytes(32)), 'payload_fingerprint' => bin2hex(random_bytes(32))]);
    }
}
