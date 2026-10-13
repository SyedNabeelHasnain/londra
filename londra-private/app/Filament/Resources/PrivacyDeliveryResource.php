<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\PrivacyDelivery;
use App\Jobs\SendPrivacyEmail;
use App\Services\PublicationState;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
final class PrivacyDeliveryResource extends Resource
{
    protected static ?string $model=PrivacyDelivery::class;
    protected static ?string $navigationLabel='Privacy email queue';
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-envelope-open';
    protected static string|\UnitEnum|null $navigationGroup='Client enquiries';
    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function shouldRegisterNavigation(): bool { return \Illuminate\Support\Facades\Schema::hasTable('privacy_deliveries'); }
    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('dataRequest.reference')->label('Request')->searchable(),TextColumn::make('kind'),TextColumn::make('status')->badge(),TextColumn::make('attempts'),TextColumn::make('smtp_accepted_at')->label('Accepted by SMTP')->dateTime('d M Y H:i')->timezone('Europe/London'),TextColumn::make('last_error_class')->toggleable(isToggledHiddenByDefault:true)])
            ->filters([SelectFilter::make('status')->options(['pending'=>'Pending','queued'=>'Queued','retrying'=>'Retrying','failed'=>'Failed','smtp_accepted'=>'Accepted by SMTP','cancelled'=>'Cancelled'])])
            ->recordActions([Action::make('retry')->disabled(fn()=>!app(PublicationState::class)->mailReady())->tooltip(fn()=>app(PublicationState::class)->mailReady()?null:'Enable notifications and verify the current Zoho SMTP configuration first.')->requiresConfirmation()->modalDescription('Check the mailbox before retrying. SMTP acceptance can be uncertain after a transport interruption, so a retry may create a duplicate email.')->visible(fn(PrivacyDelivery $record)=>in_array($record->status,['pending','failed'],true)&&!$record->smtp_accepted_at)->action(function(PrivacyDelivery $record):void{
                abort_unless(app(PublicationState::class)->mailReady(),409);
                DB::transaction(function()use($record):void{$delivery=PrivacyDelivery::lockForUpdate()->findOrFail($record->id);if(!in_array($delivery->status,['pending','failed'],true)||$delivery->smtp_accepted_at){return;}SendPrivacyEmail::dispatch($delivery->id)->onConnection('database')->beforeCommit();$delivery->update(['status'=>'queued','queued_at'=>now()]);});
            })])->defaultSort('created_at','desc')->poll('30s');
    }
    public static function getPages(): array { return ['index'=>\App\Filament\Resources\PrivacyDeliveryResource\Pages\ListPrivacyDeliveries::route('/')]; }
}
