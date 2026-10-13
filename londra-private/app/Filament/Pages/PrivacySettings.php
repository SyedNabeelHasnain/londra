<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Models\Setting;
use App\Services\PrivacyConfiguration;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
final class PrivacySettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Privacy operations';
    protected string $view = 'filament.pages.privacy-settings';
    public ?array $data = [];
    public function mount(): void { $this->form->fill(app(PrivacyConfiguration::class)->values()); }
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('retention_days')->label('Days after the last substantive customer communication')->numeric()->integer()->minValue(1)->maxValue(3650)->required()->helperText('180 days is the previously proposed operational period. Approve a period appropriate to the business and make the published notice match.'),
            Toggle::make('retention_approved')->label('The business has approved this retention rule'),
            Textarea::make('mailbox_process')->label('Zoho mailbox, sent messages, trash, attachments and export cleanup procedure')->maxLength(5000),
            Toggle::make('mailbox_process_confirmed')->label('The mailbox and export cleanup process is documented and in use'),
            Textarea::make('backup_process')->label('Backup expiry and restore suppression procedure')->maxLength(5000),
            Toggle::make('backup_process_confirmed')->label('Backup expiry and restore suppression have been checked'),
            Toggle::make('retention_enabled')->label('Enable scheduled enquiry deletion'),
        ])->statePath('data');
    }
    public function save(): void
    {
        $data = $this->form->getState();
        if (($data['mailbox_process_confirmed'] && !trim((string) ($data['mailbox_process'] ?? ''))) || ($data['backup_process_confirmed'] && !trim((string) ($data['backup_process'] ?? '')))) { throw \Illuminate\Validation\ValidationException::withMessages(['data.mailbox_process' => 'Describe the confirmed mailbox and backup procedures.']); }
        if ($data['retention_enabled'] && (!$data['retention_approved'] || !$data['mailbox_process_confirmed'] || !$data['backup_process_confirmed'])) { throw \Illuminate\Validation\ValidationException::withMessages(['data.retention_enabled' => 'Approve the rule and confirm both external-data processes before enabling deletion.']); }
        $old = app(PrivacyConfiguration::class)->values();
        if ((int) $old['retention_days'] !== (int) $data['retention_days']) {
            $privacy = \App\Models\Page::where('key', 'privacy')->first();
            $privacy?->update(['published' => false, 'approved_at' => null]);
            $privacy?->translations()->update(['ready' => false]);
        }
        Setting::updateOrCreate(['key' => 'privacy-operations'], ['value' => $data]);
        Notification::make()->title('Privacy operations saved')->success()->send();
    }
}
