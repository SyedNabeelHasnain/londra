<?php
declare(strict_types=1);
namespace App\Filament\Resources\RedirectResource\Pages;
use App\Filament\Resources\RedirectResource;
use Filament\Resources\Pages\EditRecord;
final class EditRedirect extends EditRecord
{
    protected static string $resource=RedirectResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\DeleteAction::make()->modalDescription('Remove this redirect. The old URL will no longer forward to the selected page.')]; }
}
