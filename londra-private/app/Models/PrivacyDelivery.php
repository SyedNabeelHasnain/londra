<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class PrivacyDelivery extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['queued_at' => 'datetime', 'smtp_accepted_at' => 'datetime']; }
    public function dataRequest(): BelongsTo { return $this->belongsTo(DataRequest::class); }
}
