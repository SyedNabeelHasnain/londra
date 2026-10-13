<?php
declare(strict_types=1);
namespace App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource;
use Filament\Resources\Pages\ListRecords;
final class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
