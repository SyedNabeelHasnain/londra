<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class Setting extends Model
{
    protected $primaryKey = 'key'; public $incrementing = false; protected $keyType = 'string';
    protected $fillable = ['key', 'value'];
    protected function casts(): array { return ['value' => 'array']; }
}
