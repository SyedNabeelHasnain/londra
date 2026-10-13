<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class InquiryNote extends Model
{
    protected $fillable = ['inquiry_id', 'user_id', 'body'];
    protected $hidden = ['body'];
    protected function casts(): array { return ['body' => 'encrypted']; }
    public function inquiry(): BelongsTo { return $this->belongsTo(Inquiry::class); }
}
