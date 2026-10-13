<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Domain\InquiryStatus;
use App\Models\Inquiry;
use App\Filament\Resources\InquiryResource\Pages;
use Filament\Actions\{Action,EditAction};
use Filament\Forms\Components\{TextInput,Textarea,Select,DateTimePicker};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
final class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;
    protected static ?string $navigationLabel = 'Enquiry desk';
    protected static ?string $slug = 'inquiries';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';
    protected static string|\UnitEnum|null $navigationGroup = 'Client enquiries';
    protected static ?int $navigationSort = 0;
    public static function canCreate(): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function getRelations(): array { return [\App\Filament\Resources\InquiryResource\RelationManagers\NotesRelationManager::class]; }
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enquiry details')->schema([
                TextInput::make('reference')->disabled()->dehydrated(false), TextInput::make('type')->disabled()->dehydrated(false),
                TextInput::make('name')->disabled()->dehydrated(false), TextInput::make('email')->disabled()->dehydrated(false),
                TextInput::make('phone')->disabled()->dehydrated(false), TextInput::make('locale')->disabled()->dehydrated(false),
                TextInput::make('subject')->label('Stored enquiry subject')->visible(fn(?Inquiry $record) => trim((string) $record?->subject) !== '')->disabled()->dehydrated(false), Textarea::make('message')->label('Stored enquiry message')->visible(fn(?Inquiry $record) => trim((string) $record?->message) !== '')->disabled()->dehydrated(false),
                TextInput::make('page_key')->disabled()->dehydrated(false), TextInput::make('service_key')->disabled()->dehydrated(false),
            ])->columns(2),
            Section::make('Retention and legal basis')->schema([
                DateTimePicker::make('last_substantive_contact_at')->label('Last substantive customer communication, London time')->timezone('Europe/London')->seconds(false)->required(),
                DateTimePicker::make('converted_at')->label('Confirmed-service date, if applicable')->timezone('Europe/London')->seconds(false),
                DateTimePicker::make('legal_hold_at')->label('Legal hold begins')->timezone('Europe/London')->seconds(false),
                Textarea::make('legal_hold_reason')->label('Specific legal-hold reason')->maxLength(2000),
                DateTimePicker::make('active_until')->label('Keep active until, if justified')->timezone('Europe/London')->seconds(false),
                Textarea::make('active_reason')->label('Why continued enquiry retention is necessary')->maxLength(2000),
            ]),
            Section::make('Follow-up')->schema([
                Select::make('status')->options(InquiryStatus::options())->required(),
                DateTimePicker::make('follow_up_at')->label('Follow up, London time')->timezone('Europe/London')->seconds(false),
            ]),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable()->copyable(), TextColumn::make('type')->badge(),
            TextColumn::make('name'), TextColumn::make('email')->searchable(query: fn($query,string $search)=>$query->where('email_lookup',hash_hmac('sha256',mb_strtolower(trim($search)),config('app.key')))),
            TextColumn::make('locale'), TextColumn::make('status')->formatStateUsing(fn(InquiryStatus $state)=>$state->label())->badge(),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Europe/London')->sortable(),
            TextColumn::make('follow_up_at')->dateTime('d M Y H:i')->timezone('Europe/London')->sortable(),
        ])->filters([SelectFilter::make('type')->options(['booking'=>'Booking enquiries','contact'=>'Contact enquiries']),SelectFilter::make('status')->options(InquiryStatus::options()),SelectFilter::make('locale')->options(['en'=>'English','it'=>'Italian','es'=>'Spanish'])])->recordActions([EditAction::make(),Action::make('export')->label('Export this enquiry')->requiresConfirmation()->modalDescription('This file contains customer contact information. Store it securely.')->action(fn(Inquiry $record)=>app(\App\Services\InquiryExport::class)->one($record))])->defaultSort('created_at','desc')->poll('30s');
    }
    public static function getPages(): array { return ['index'=>Pages\ListInquiries::route('/'),'edit'=>Pages\EditInquiry::route('/{record}/edit')]; }
}
