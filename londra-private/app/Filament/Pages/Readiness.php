<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Services\ReadinessReport;
use Filament\Pages\Page;
final class Readiness extends Page
{
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup='Administration';
    protected static ?string $navigationLabel = 'Release checks';
    protected string $view='filament.pages.readiness';
    public function checks(): array { return app(ReadinessReport::class)->checks(); }
    public function cronArguments(): string { return escapeshellarg(base_path('artisan')).' londra:cron'; }
}
