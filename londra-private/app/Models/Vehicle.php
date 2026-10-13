<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use App\Services\MediaReferences;
final class Vehicle extends Model
{
    protected $fillable = ['code', 'page_id', 'name', 'passengers', 'image_key', 'large_luggage', 'cabin_luggage', 'luggage_approved', 'active', 'sort_order'];
    protected function casts(): array { return ['passengers' => 'integer', 'active' => 'boolean', 'luggage_approved' => 'boolean']; }
    public function page(): BelongsTo { return $this->belongsTo(Page::class); }
    public static function coreCodes(): array { return array_column(json_decode((string) file_get_contents(database_path('content/vehicles.json')), true, 512, JSON_THROW_ON_ERROR), 'code'); }
    public function canBeDeleted(): bool { return static::query()->whereKey($this->id)->whereNotIn('code',self::coreCodes())->where('active',false)->whereHas('page',fn($query)=>$query->where('published',false))->exists(); }
    public function delete() { return $this->getConnection()->transaction(fn() => parent::delete()); }
    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if (!preg_match('/^[a-z0-9-]{1,40}$/D', (string) $vehicle->code) || ($vehicle->exists && $vehicle->isDirty('code'))) { throw ValidationException::withMessages(['code' => 'Keep an existing vehicle code unchanged. Use lowercase letters, numbers and hyphens for a new code.']); }
            if (!Page::whereKey($vehicle->page_id)->where('kind','vehicle')->exists() || static::where('page_id',$vehicle->page_id)->when($vehicle->exists, fn($query) => $query->whereKeyNot($vehicle->id))->exists()) { throw ValidationException::withMessages(['page_id' => 'Choose a vehicle detail page that is not assigned to another vehicle.']); }
            if (!app(MediaReferences::class)->isAvailable($vehicle->image_key)) { throw ValidationException::withMessages(['image_key' => 'Choose an available vehicle photograph.']); }
            if ($vehicle->passengers < 1 || $vehicle->passengers > 99) { throw ValidationException::withMessages(['passengers' => 'Guest capacity must be between 1 and 99, excluding the driver.']); }
            if ($vehicle->luggage_approved && ($vehicle->large_luggage === null || $vehicle->cabin_luggage === null)) { throw ValidationException::withMessages(['luggage_approved' => 'Confirm both luggage allowances before publishing them.']); }
        });
        static::deleting(function (Vehicle $vehicle): void {
            $current=static::query()->find($vehicle->id);
            $page=$current?Page::query()->lockForUpdate()->find($current->page_id):null;
            $locked=static::query()->lockForUpdate()->find($vehicle->id);
            if(!$locked||!$page||$locked->page_id!==$page->id||$locked->active||$page->published||in_array($locked->code,self::coreCodes(),true)){throw ValidationException::withMessages(['vehicle'=>'Only an inactive custom vehicle with an unpublished detail page can be deleted. The four website vehicle categories are retained; deactivate or update them instead.']);}
        });
    }
}
