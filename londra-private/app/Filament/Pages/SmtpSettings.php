<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Contracts\EmailTransport;
use App\Models\Setting;
use App\Services\SmtpHealth;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
final class SmtpSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Zoho Mail SMTP check';
    protected string $view = 'filament.pages.smtp-settings';
    public function host(): string { return (string) config('mail.mailers.smtp.host'); }
    public function port(): int { return (int) config('mail.mailers.smtp.port'); }
    public function sender(): string { return (string) config('mail.from.address'); }
    public function verified(): bool { return app(SmtpHealth::class)->verified(); }
    public function sendTest(): void
    {
        abort_unless(auth()->check(), 403);
        $lock = \Illuminate\Support\Facades\Cache::lock('londra-smtp-check', 60);
        if (!$lock->get()) { throw \Illuminate\Validation\ValidationException::withMessages(['smtp' => 'A mail check is already running.']); }
        try {
            $text = 'This is an administrator-requested test of the existing Londra Transfers Zoho Mail SMTP connection. It contains no customer information and does not confirm a transport booking.';
            app(EmailTransport::class)->send((string) config('londra.contact.email'), 'Londra Transfers | Zoho Mail SMTP test', '<p>'.e($text).'</p>', $text);
            Setting::updateOrCreate(['key' => 'smtp-health'], ['value' => ['smtp_accepted_at' => now()->toIso8601String(), 'configuration_fingerprint' => app(SmtpHealth::class)->fingerprint()]]);
            Notification::make()->title('Accepted by SMTP')->body('Check info@londratransfers.co.uk for arrival before confirming the mail check in Website operation.')->success()->send();
        } catch (\Throwable) {
            Notification::make()->title('Zoho SMTP check failed')->body('Check the existing private mailbox, host, port and application-password settings. Credentials are not shown.')->danger()->send();
        } finally { $lock->release(); }
    }
}
