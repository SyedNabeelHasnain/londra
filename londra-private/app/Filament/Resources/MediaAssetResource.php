<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\MediaAsset;
use App\Filament\Resources\MediaAssetResource\Pages;
use Filament\Actions\{Action,EditAction,DeleteAction};
use App\Services\MediaReferences;
use Filament\Forms\Components\{TextInput,FileUpload};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
final class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;
    protected static ?string $navigationLabel = 'Media library';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static string|\UnitEnum|null $navigationGroup = 'Content';
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return $record instanceof MediaAsset && $record->canBeDeleted(); }
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->required()->maxLength(80)->regex('/^[a-z0-9-]+$/D')->unique(ignoreRecord:true)->disabledOn('edit')->helperText('The website’s photograph definitions are retained. Their photographs and details can be replaced; unused custom assets can be deleted.'),
            TextInput::make('public_path')->disabled()->dehydrated(false),
            FileUpload::make('upload_path')->label('Replacement or new photograph')->disk('media')->image()->acceptedFileTypes(['image/jpeg','image/png','image/webp'])->maxSize(10240)
                ->required(fn(?MediaAsset $record) => !$record?->public_path)->helperText('PNG, JPEG or WebP, up to 10 MB, 40 megapixels and 16,000 pixels per side. Responsive WebP files are created and verified automatically.')
                ->getUploadedFileNameForStorageUsing(fn(TemporaryUploadedFile $file)=>(string) Str::uuid().'.'.match($file->getMimeType()){'image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp',default=>throw new \InvalidArgumentException('Unsupported image type.')}),
            TextInput::make('alt_en')->required()->maxLength(255),TextInput::make('alt_it')->required()->maxLength(255),TextInput::make('alt_es')->required()->maxLength(255),
            TextInput::make('provenance')->label('Source, rights or approval note')->maxLength(255),
        ])->columns(2);
    }
    public static function table(Table $table): Table { return $table->columns([TextColumn::make('key')->searchable(),TextColumn::make('width'),TextColumn::make('height'),TextColumn::make('provenance')->limit(65)])->recordActions([EditAction::make(),Action::make('references')->label('Where used')->modalHeading('Image references')->modalDescription(fn(MediaAsset $record) => ($refs = app(MediaReferences::class)->forKey($record->key)) === [] ? 'This image is not referenced by a page, vehicle or shared photograph component.' : implode('; ', $refs))->modalSubmitAction(false)->modalCancelActionLabel('Close'),DeleteAction::make()->authorize(fn(MediaAsset $record)=>self::canDelete($record))->visible(fn(MediaAsset $record)=>self::canDelete($record))->modalDescription('Permanently delete this unreferenced custom media record and its uploaded responsive files. Website definitions and referenced photographs are retained.')]); }
    public static function getPages(): array { return ['index'=>Pages\ListMediaAssets::route('/'),'create'=>Pages\CreateMediaAsset::route('/create'),'edit'=>Pages\EditMediaAsset::route('/{record}/edit')]; }
}
