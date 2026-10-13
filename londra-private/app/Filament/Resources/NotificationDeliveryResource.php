<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\NotificationDelivery;
use App\Jobs\SendInquiryEmail;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
final class NotificationDeliveryResource extends Resource
{
    protected static ?string $model=NotificationDelivery::class;
    protected static ?string $navigationLabel='Email queue';
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-envelope';
    protected static string|\UnitEnum|null $navigationGroup='Client enquiries';
    public static function canCreate(): bool{return false;}
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool{return false;}
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool{return false;}
    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('inquiry.reference')->searchable(),TextColumn::make('kind'),TextColumn::make('status')->badge(),TextColumn::make('attempts'),TextColumn::make('smtp_accepted_at')->label('Accepted by SMTP')->dateTime('d M Y H:i')->timezone('Europe/London'),TextColumn::make('last_error_class')->toggleable(isToggledHiddenByDefault:true)])
            ->filters([SelectFilter::make('status')->options(['pending'=>'Pending','queued'=>'Queued','retrying'=>'Retrying','failed'=>'Failed','smtp_accepted'=>'Accepted by SMTP'])])
            ->recordActions([Action::make('retry')->disabled(fn()=>!app(\App\Services\PublicationState::class)->mailReady())->tooltip(fn()=>app(\App\Services\PublicationState::class)->mailReady()?null:'Enable notifications and verify the current Zoho SMTP configuration first.')->requiresConfirmation()->modalDescription('A transport interruption can make acceptance uncertain. Check the mailbox before retrying to avoid duplicate emails.')->visible(fn(NotificationDelivery $record)=>in_array($record->status,['pending','failed'],true)&&!$record->smtp_accepted_at)->action(function(NotificationDelivery $record):void{
                abort_unless(app(\App\Services\PublicationState::class)->mailReady(),409);
                DB::transaction(function()use($record):void{$delivery=NotificationDelivery::lockForUpdate()->findOrFail($record->id);if(!in_array($delivery->status,['pending','failed'],true)||$delivery->smtp_accepted_at){return;}SendInquiryEmail::dispatch($delivery->id)->onConnection('database')->beforeCommit();$delivery->update(['status'=>'queued','queued_at'=>now()]);});
            })])->defaultSort('created_at','desc')->poll('30s');
    }
    public static function getPages(): array{return ['index'=>\App\Filament\Resources\NotificationDeliveryResource\Pages\ListNotificationDeliveries::route('/')];}
}
