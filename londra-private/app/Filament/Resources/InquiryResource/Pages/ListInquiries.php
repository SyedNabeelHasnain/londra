<?php
declare(strict_types=1);
namespace App\Filament\Resources\InquiryResource\Pages;
use App\Filament\Resources\InquiryResource;
use Filament\Resources\Pages\ListRecords;
final class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;
}
