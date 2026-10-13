<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\Vehicle;
use App\Models\Page;
use App\Models\MediaAsset;
use App\Filament\Resources\VehicleResource\Pages;
use Filament\Actions\{EditAction,DeleteAction};
use Filament\Forms\Components\{TextInput,Select,Toggle};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn,IconColumn};
final class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Content';
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return $record instanceof Vehicle && $record->canBeDeleted(); }
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(40)->unique(ignoreRecord:true)->regex('/^[a-z0-9-]+$/D')->disabledOn('edit')->helperText('The four website vehicle categories are retained. Edit their approved details or deactivate them; only eligible custom categories can be deleted.'),
            TextInput::make('name')->label('Administrator vehicle name')->helperText('The public name in each language comes from the linked vehicle page heading.')->required()->maxLength(255),
            Select::make('page_id')->label('Vehicle detail page')->options(fn()=>Page::where('kind','vehicle')->pluck('key','id'))->required(),
            Select::make('image_key')->label('Fleet and comparison photograph')->helperText('Used in the fleet selector, gallery, comparison and related vehicles. The detail-page main photograph is set separately under Pages.')->options(fn()=>MediaAsset::pluck('key','key'))->required()->searchable(),
            TextInput::make('passengers')->label('Guest capacity, excluding the driver')->numeric()->integer()->minValue(1)->maxValue(99)->required(),
            TextInput::make('large_luggage')->label('Large suitcases, only when confirmed')->numeric()->integer()->minValue(0)->maxValue(99)->nullable(),
            TextInput::make('cabin_luggage')->label('Cabin bags, only when confirmed')->numeric()->integer()->minValue(0)->maxValue(99)->nullable(),
            Toggle::make('luggage_approved')->label('Publish these confirmed luggage allowances')->default(false),
            Toggle::make('active')->default(true),TextInput::make('sort_order')->numeric()->minValue(0)->maxValue(999)->default(0),
        ])->columns(2);
    }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable(),TextColumn::make('passengers')->label('Guests'),IconColumn::make('active')->boolean()])->recordActions([EditAction::make(),DeleteAction::make()->authorize(fn(Vehicle $record)=>self::canDelete($record))->visible(fn(Vehicle $record)=>self::canDelete($record))->modalDescription('Permanently delete this inactive custom vehicle after its detail page has been unpublished. The four website categories are retained.')])->defaultSort('sort_order'); }
    public static function getPages(): array { return ['index'=>Pages\ListVehicles::route('/'),'create'=>Pages\CreateVehicle::route('/create'),'edit'=>Pages\EditVehicle::route('/{record}/edit')]; }
}
