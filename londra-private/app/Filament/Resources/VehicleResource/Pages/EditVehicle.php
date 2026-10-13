<?php
declare(strict_types=1);
namespace App\Filament\Resources\VehicleResource\Pages;
use App\Filament\Resources\VehicleResource;
use Filament\Resources\Pages\EditRecord;
final class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\DeleteAction::make()->authorize(fn()=>VehicleResource::canDelete($this->record))->visible(fn()=>VehicleResource::canDelete($this->record))->modalDescription('Permanently delete this inactive custom vehicle after its detail page has been unpublished. The four website categories are retained.')]; }
}
