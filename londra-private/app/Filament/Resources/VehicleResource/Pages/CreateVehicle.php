<?php
declare(strict_types=1);
namespace App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource;
use Filament\Resources\Pages\CreateRecord;
final class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;
}
