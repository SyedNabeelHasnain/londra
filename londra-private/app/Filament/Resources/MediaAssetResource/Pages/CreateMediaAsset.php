<?php
declare(strict_types=1);
namespace App\Filament\Resources\MediaAssetResource\Pages;
use App\Filament\Resources\MediaAssetResource;
use Filament\Resources\Pages\CreateRecord;
final class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;
}
