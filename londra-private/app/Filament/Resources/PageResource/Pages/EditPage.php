<?php
declare(strict_types=1);
namespace App\Filament\Resources\PageResource\Pages;
use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
final class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;
    protected function handleRecordUpdate(Model $record, array $data): Model { $record->refresh(); return parent::handleRecordUpdate($record, $data); }
    protected function afterSave(): void { $this->record->refresh(); }
    protected function getHeaderActions(): array { return [\Filament\Actions\Action::make('publish')->visible(fn() => !$this->record->published)->requiresConfirmation()->action(fn()=>app(\App\Actions\PublishPage::class)->handle($this->record)), \Filament\Actions\DeleteAction::make()->authorize(fn()=>PageResource::canDelete($this->record))->visible(fn()=>PageResource::canDelete($this->record))->modalDescription('Permanently delete this unpublished custom page and its translations and revisions. Required pages and referenced pages are retained.')]; }
}
