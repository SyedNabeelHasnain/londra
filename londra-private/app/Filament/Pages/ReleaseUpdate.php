<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Models\Setting;
use App\Services\ReleaseUpdater;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
final class ReleaseUpdate extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Application update';
    protected string $view = 'filament.pages.release-update';
    public bool $backupConfirmed = false;
    public function applied(): bool { return Setting::find('release-'.ReleaseUpdater::VERSION) !== null; }
    public function applyUpdate(): void
    {
        abort_unless(auth()->check(), 403);
        if (!$this->backupConfirmed) { throw \Illuminate\Validation\ValidationException::withMessages(['backupConfirmed' => 'Confirm the private configuration and database backup first.']); }
        app(ReleaseUpdater::class)->run();
        Notification::make()->title('Application update completed')->body('The public design and release copy were updated. Configuration, administrator, enquiry records and individual content edits were retained. Review any policy changes before publication.')->success()->send();
    }
}
