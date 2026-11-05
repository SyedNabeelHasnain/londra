<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Models\Setting;
use App\Services\PublicationState;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
final class LaunchSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Website operation';
    protected string $view = 'filament.pages.launch-settings';
    public ?array $data = [];
    public function mount(): void
    {
        $keys = ['accept_inquiries', 'send_notifications', 'publication_approved', 'privacy_approved', 'indexing_enabled'];
        $defaults = []; foreach ($keys as $key) { $defaults[$key] = (bool) config('londra.'.$key); }
        $this->form->fill((Setting::find('launch-operations')?->value ?? []) + $defaults);
    }
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('publication_approved')->label('Business content and the three language versions have been approved'),
            Toggle::make('privacy_approved')->label('Privacy processing, supplier arrangements and the UK/EEA position have been reviewed'),
            Textarea::make('privacy_review_record')->label('Review record: Hostinger and Zoho account regions, processor agreements, safeguards, retention and EEA representative or documented exemption')->rows(6)->maxLength(10000),
            Toggle::make('smtp_verified')->label('Existing Zoho Mail SMTP has been tested for administrator and customer mail'),
            Toggle::make('send_notifications')->label('Enable transactional notifications through the existing Zoho SMTP configuration'),
            Toggle::make('accept_inquiries')->label('Accept public name, email and phone enquiries'),
            Toggle::make('indexing_enabled')->label('Allow search-engine indexing of published pages'),
        ])->statePath('data');
    }
    public function save(): void
    {
        $data = $this->form->getState();
        if (($data['accept_inquiries'] || $data['indexing_enabled']) && (!$data['publication_approved'] || !app(PublicationState::class)->policiesReady())) { throw \Illuminate\Validation\ValidationException::withMessages(['data.publication_approved' => 'Review and approve the business content and all five policies in three languages first.']); }
        if ($data['accept_inquiries'] && (!$data['privacy_approved'] || mb_strlen(trim((string) ($data['privacy_review_record'] ?? ''))) < 20)) { throw \Illuminate\Validation\ValidationException::withMessages(['data.privacy_review_record' => 'Record the actual privacy review before enabling enquiry collection.']); }
        if ($data['send_notifications'] && (!$data['smtp_verified'] || !app(\App\Services\SmtpHealth::class)->verified())) { throw \Illuminate\Validation\ValidationException::withMessages(['data.smtp_verified' => 'Run the Zoho Mail SMTP check and confirm inbox arrival first.']); }
        if ($data['accept_inquiries'] && !$data['send_notifications']) { throw \Illuminate\Validation\ValidationException::withMessages(['data.send_notifications' => 'Enable the checked transactional email configuration before accepting enquiries.']); }
        if ($data['accept_inquiries'] && !app(\App\Services\PrivacyConfiguration::class)->retentionEnabled()) { throw \Illuminate\Validation\ValidationException::withMessages(['data.privacy_review_record' => 'Configure and approve retention in Privacy operations before accepting enquiries.']); }
        Setting::updateOrCreate(['key' => 'launch-operations'], ['value' => $data + ['updated_by' => auth()->id(), 'reviewed_at' => now()->toIso8601String()]]);
        foreach (['accept_inquiries', 'send_notifications', 'publication_approved', 'privacy_approved', 'indexing_enabled'] as $key) { config(['londra.'.$key => (bool) $data[$key]]); }
        Notification::make()->title('Website operation settings saved')->success()->send();
    }
}
