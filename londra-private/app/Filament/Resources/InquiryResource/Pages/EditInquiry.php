<?php
declare(strict_types=1);
namespace App\Filament\Resources\InquiryResource\Pages;
use App\Filament\Resources\InquiryResource;
use Filament\Resources\Pages\EditRecord;
final class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['name','email','phone','subject','message','legal_hold_reason','active_reason'] as $field) { $data[$field]=$this->record->{$field}; }
        return $data;
    }
}
