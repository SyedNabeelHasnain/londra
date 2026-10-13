<?php
declare(strict_types=1);
namespace App\Filament\Resources\RedirectResource\Pages;
use App\Filament\Resources\RedirectResource;
use Filament\Resources\Pages\ListRecords;
final class ListRedirects extends ListRecords
{
    protected static string $resource=RedirectResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
