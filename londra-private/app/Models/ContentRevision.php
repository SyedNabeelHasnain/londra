<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class ContentRevision extends Model
{
    protected $fillable = ['page_id', 'locale', 'snapshot', 'user_id'];
    protected function casts(): array { return ['snapshot' => 'array']; }
    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Page::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
}
