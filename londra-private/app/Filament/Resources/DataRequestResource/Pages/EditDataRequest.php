<?php
declare(strict_types=1);
namespace App\Filament\Resources\DataRequestResource\Pages;
use App\Filament\Resources\DataRequestResource;
use Filament\Resources\Pages\EditRecord;
final class EditDataRequest extends EditRecord
{
    protected static string $resource = DataRequestResource::class;
    protected function mutateFormDataBeforeFill(array $data): array { $data['email'] = $this->record->email; $data['decision_reason'] = $this->record->decision_reason; return $data; }
    protected function getHeaderActions(): array { return DataRequestResource::actions(); }
    protected function getFormActions(): array { return []; }
}
