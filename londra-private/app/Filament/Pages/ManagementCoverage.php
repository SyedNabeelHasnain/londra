<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Services\CmsManagement;
use Filament\Pages\Page;
final class ManagementCoverage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup='Administration';
    protected static ?string $navigationLabel='Management coverage';
    protected string $view='filament.pages.management-coverage';
    public function matrix(): array { return app(CmsManagement::class)->matrix(); }
}
