<?php
declare(strict_types=1);
namespace App\Filament\Resources\DataRequestResource\Pages;
use App\Filament\Resources\DataRequestResource;
use Filament\Resources\Pages\ListRecords;
final class ListDataRequests extends ListRecords { protected static string $resource = DataRequestResource::class; }
