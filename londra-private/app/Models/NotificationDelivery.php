<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class NotificationDelivery extends Model
{
    protected $fillable = ['inquiry_id', 'kind', 'status', 'attempts', 'queued_at', 'smtp_accepted_at', 'last_error_class'];
    protected function casts(): array { return ['queued_at' => 'datetime', 'smtp_accepted_at' => 'datetime']; }
    public function inquiry(): BelongsTo { return $this->belongsTo(Inquiry::class); }
}
