<?php
declare(strict_types=1);
namespace App\Filament\Resources\PageResource\Pages;
use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\ListRecords;
final class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
