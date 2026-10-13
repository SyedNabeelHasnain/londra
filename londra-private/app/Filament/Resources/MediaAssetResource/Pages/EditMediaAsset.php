<?php
declare(strict_types=1);
namespace App\Filament\Resources\MediaAssetResource\Pages;
use App\Filament\Resources\MediaAssetResource;
use Filament\Resources\Pages\EditRecord;
final class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\DeleteAction::make()->authorize(fn()=>MediaAssetResource::canDelete($this->record))->visible(fn()=>MediaAssetResource::canDelete($this->record))->modalDescription('Permanently delete this unreferenced custom media record and its uploaded responsive files. Website definitions and referenced photographs are retained.')]; }
}
