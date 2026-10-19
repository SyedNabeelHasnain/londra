<?php
declare(strict_types=1);
namespace App\Filament\Pages;

use App\Models\MediaAsset;
use App\Models\Setting;
use App\Services\FleetPhotoGallery;
use Filament\Forms\Components\{Select,Textarea,Toggle};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Validation\ValidationException;

final class FleetPhotoGalleries extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static string|\UnitEnum|null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Vehicle photo galleries';
    protected string $view = 'filament.pages.fleet-photo-galleries';
    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::find('fleet-photo-galleries')?->value ?? [];
        $initial = [];
        foreach (FleetPhotoGallery::APPROVED_REFERENCES as $code => $references) {
            $initial[$code.'_photos'] = $settings[$code]['photos'] ?? [];
            $initial[$code.'_rights_confirmed'] = (bool) ($settings[$code]['rights_confirmed'] ?? false);
            $initial[$code.'_rights_note'] = $settings[$code]['rights_note'] ?? '';
        }
        $this->form->fill($initial);
    }

    public function form(Schema $schema): Schema
    {
        $parts = [];
        foreach (FleetPhotoGallery::APPROVED_REFERENCES as $code => $references) {
            $label = match ($code) {
                'e-class' => 'Mercedes-Benz E-Class',
                's-class' => 'Mercedes-Benz S-Class',
                'v-class' => 'Mercedes-Benz V-Class',
                'vito' => 'Mercedes-Benz Vito',
                default => $code
            };
            $keys = FleetPhotoGallery::allKeys($code);
            $parts[] = Section::make($label.' — '.count($keys).' approved appearance references')
                ->description('Reference IDs: '.implode(', ', $references).'. Upload individual photographs in Media library using the exact keys below. Appearance approval alone is NOT publication permission.')
                ->schema([
                    Select::make($code.'_photos')->label('Gallery images in approved reference order')
                        ->multiple()->searchable()
                        ->options(static function () use ($keys): array {
                            return MediaAsset::query()->whereIn('key', $keys)->pluck('key', 'key')->all();
                        })
                        ->helperText('Select every one of the '.count($keys).' listed images. The gallery stays hidden until all files and rights are confirmed.'),
                    Toggle::make($code.'_rights_confirmed')->label('I confirm commercial publication and editing rights for ALL selected photos, including necessary identifiable-person consents')->default(false),
                    Textarea::make($code.'_rights_note')->label('Document the actual licence evidence, rights holder and applicable permission')->rows(3)->maxLength(4000),
                ])->columns(1);
        }
        return $schema->components($parts)->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = [];
        foreach (FleetPhotoGallery::APPROVED_REFERENCES as $code => $references) {
            $expected = FleetPhotoGallery::allKeys($code);
            $keys = array_values(array_unique(array_filter((array)($state[$code.'_photos'] ?? []), 'is_string')));
            $rights = ($state[$code.'_rights_confirmed'] ?? false) === true;
            $note = trim((string) ($state[$code.'_rights_note'] ?? ''));
            if (array_diff($keys, $expected)) {
                throw ValidationException::withMessages(['data.'.$code.'_photos' => 'Only the previously approved vehicle photo reference IDs may be used.']);
            }
            if ($rights) {
                if (count($keys) !== count($expected) || array_diff($expected, $keys)) {
                    throw ValidationException::withMessages(['data.'.$code.'_photos' => 'Upload and select every approved exterior/interior photograph for this vehicle before enabling its gallery.']);
                }
                if (mb_strlen($note) < 30) {
                    throw ValidationException::withMessages(['data.'.$code.'_rights_note' => 'Record the actual rights holder, commercial licence and identifiable-person permissions.']);
                }
                foreach ($keys as $key) {
                    $photo = MediaAsset::where('key', $key)->first();
                    if (!$photo || !app(\App\Services\MediaReferences::class)->assetAvailable($photo) || mb_strlen(trim((string)$photo->provenance)) < 20) {
                        throw ValidationException::withMessages(['data.'.$code.'_photos' => 'Every gallery photograph needs a verified media file and its own recorded licence provenance before publication.']);
                    }
                }
            }
            $settings[$code] = ['photos' => $keys, 'rights_confirmed' => $rights, 'rights_note' => $note];
        }
        Setting::updateOrCreate(['key'=>'fleet-photo-galleries'], ['value'=>$settings]);
        Notification::make()->title('Fleet gallery settings saved')->success()->send();
    }
}
