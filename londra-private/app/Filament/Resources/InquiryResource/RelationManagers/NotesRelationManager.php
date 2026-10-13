<?php
declare(strict_types=1);
namespace App\Filament\Resources\InquiryResource\RelationManagers;
use App\Models\InquiryNote;
use Filament\Actions\{CreateAction,EditAction,DeleteAction};
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
final class NotesRelationManager extends RelationManager
{
    protected static string $relationship='notes';
    protected static ?string $title='Private follow-up notes';
    public function form(Schema $schema): Schema { return $schema->components([Textarea::make('body')->label('Note')->required()->maxLength(5000)->rows(5)]); }
    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('body')->columns([TextColumn::make('body')->label('Note')->wrap()->limit(160),TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Europe/London')->sortable(),TextColumn::make('updated_at')->dateTime('d M Y H:i')->timezone('Europe/London')])->headerActions([CreateAction::make()->mutateDataUsing(fn(array $data)=>[...$data,'user_id'=>auth()->id()])])->recordActions([EditAction::make()->mutateRecordDataUsing(fn(array $data,InquiryNote $record)=>[...$data,'body'=>$record->body]),DeleteAction::make()->modalDescription('Permanently delete this private note. The received enquiry remains unchanged.')])->defaultSort('created_at','desc');
    }
}
