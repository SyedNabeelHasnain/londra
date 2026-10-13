<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\DataRequest;
use App\Services\DataRequestReview;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
final class DataRequestResource extends Resource
{
    protected static ?string $model = DataRequest::class;
    protected static ?string $slug = 'data-requests';
    protected static ?string $navigationLabel = 'Data deletion requests';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Client enquiries';
    public static function canCreate(): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function shouldRegisterNavigation(): bool { return \Illuminate\Support\Facades\Schema::hasTable('data_requests'); }
    public static function form(Schema $schema): Schema
    {
        $fields = [TextInput::make('reference'), TextInput::make('email'), TextInput::make('locale'), TextInput::make('status'), TextInput::make('verified_at'), TextInput::make('due_at'), TextInput::make('database_erased_at'), TextInput::make('completed_at'), TextInput::make('erased_count'), Textarea::make('decision_reason')];
        foreach ($fields as $field) { $field->disabled()->dehydrated(false); }
        return $schema->components($fields)->columns(2);
    }
    public static function actions(): array
    {
        return [
            Action::make('erase')->label('Approve and erase database records')->requiresConfirmation()->modalDescription('Permanently erase matching enquiries, notes and notifications. Confirm identity and the legal basis first. A legal hold or confirmed service will stop the operation.')->visible(fn(DataRequest $record) => $record->status === 'in_review' && $record->verified_at)->action(fn(DataRequest $record) => app(DataRequestReview::class)->eraseDatabase($record, auth()->id())),
            Action::make('complete')->label('Complete external review and acknowledge')->schema([Checkbox::make('mailbox')->label('Relevant Zoho messages, trash, attachments and local exports have been reviewed and handled.')->accepted()->required(), Checkbox::make('backups')->label('Deleted data in backups is beyond normal use and will expire under the documented backup schedule; restore procedures prevent reintroduction.')->accepted()->required()])->requiresConfirmation()->visible(fn(DataRequest $record) => $record->status === 'database_erased')->action(fn(DataRequest $record, array $data) => app(DataRequestReview::class)->complete($record, auth()->id(), (bool) $data['mailbox'], (bool) $data['backups'])),
            Action::make('refuse')->label('Respond with a lawful retention reason')->schema([Textarea::make('decision_reason')->label('Customer-facing explanation in the selected language. This will be emailed.')->minLength(10)->maxLength(2000)->required()])->requiresConfirmation()->visible(fn(DataRequest $record) => $record->status === 'in_review')->action(fn(DataRequest $record, array $data) => app(DataRequestReview::class)->refuse($record, auth()->id(), $data['decision_reason'])),
            Action::make('retry_mail')->label('Retry a failed acknowledgement')->disabled(fn()=>!app(\App\Services\PublicationState::class)->mailReady())->tooltip(fn()=>app(\App\Services\PublicationState::class)->mailReady()?null:'Enable notifications and verify the current Zoho SMTP configuration first.')->requiresConfirmation()->modalDescription('SMTP may already have accepted a previous attempt. A retry can create a duplicate email.')->visible(fn(DataRequest $record)=>$record->deliveries()->where('status','failed')->whereNull('smtp_accepted_at')->exists())->action(function (DataRequest $record): void { abort_unless(app(\App\Services\PublicationState::class)->mailReady(),409); foreach ($record->deliveries()->where('status', 'failed')->whereNull('smtp_accepted_at')->get() as $delivery) { $delivery->update(['status' => 'pending']); } }),
        ];
    }
    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('reference')->searchable(), TextColumn::make('email'), TextColumn::make('locale'), TextColumn::make('status')->badge(), TextColumn::make('verified_at')->dateTime()->timezone('Europe/London'), TextColumn::make('due_at')->dateTime()->timezone('Europe/London')->sortable(), TextColumn::make('erased_count')])->filters([SelectFilter::make('status')->options(['received' => 'Awaiting email verification', 'in_review' => 'Awaiting review', 'database_erased' => 'External cleanup pending', 'completed' => 'Completed', 'refused' => 'Retention reason issued','cancelled'=>'Cancelled'])])->recordActions([EditAction::make(), ...self::actions()])->defaultSort('due_at')->poll('30s');
    }
    public static function getPages(): array { return ['index' => \App\Filament\Resources\DataRequestResource\Pages\ListDataRequests::route('/'), 'edit' => \App\Filament\Resources\DataRequestResource\Pages\EditDataRequest::route('/{record}/edit')]; }
}
