<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\{ContentRevision,PageTranslation,Page};
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\{Textarea,Checkbox};
use Illuminate\Support\Facades\DB;
final class ContentRevisionResource extends Resource
{
    protected static ?string $model=ContentRevision::class;
    protected static ?string $navigationLabel='Content history';
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-clock';
    protected static string|\UnitEnum|null $navigationGroup='Content';
    public static function canCreate(): bool{return false;}
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool{return false;}
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool{return false;}
    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('page.key')->searchable(),TextColumn::make('locale'),TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Europe/London'),TextColumn::make('user.name')])->recordActions([
            Action::make('inspect')->label('Inspect revision')->schema([Textarea::make('snapshot')->rows(20)->disabled()])->fillForm(fn(ContentRevision $record)=>['snapshot'=>json_encode($record->snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)])->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            Action::make('restore')->requiresConfirmation()->modalDescription(fn(ContentRevision $record)=>in_array($record->page->key,Page::requiredKeys(),true)&&$record->page->published?'Inspect the revision first and confirm that this restored language has been reviewed. The required page remains published after validation.':'Restore the text as an unreviewed translation and unpublish the page. Policies lose approval and require a fresh review.')
                ->schema([Checkbox::make('reviewed')->label('I inspected this revision and reviewed the restored text in its language.')->visible(fn(ContentRevision $record)=>in_array($record->page->key,Page::requiredKeys(),true)&&$record->page->published)->accepted()->required()])
                ->action(function(ContentRevision $record,array $data):void{DB::transaction(function()use($record,$data):void{
                $page=Page::query()->lockForUpdate()->findOrFail($record->page_id);
                $essential=$page->published&&in_array($page->key,Page::requiredKeys(),true);
                if($essential&&!($data['reviewed']??false)){throw \Illuminate\Validation\ValidationException::withMessages(['reviewed'=>'Review the inspected revision before restoring this required page.']);}
                $translation=PageTranslation::where('page_id',$record->page_id)->where('locale',$record->locale)->lockForUpdate()->firstOrFail();
                $snapshot=array_intersect_key($record->snapshot,array_flip(['title','eyebrow','intro','detail_heading','body','highlights','faq_items','seo_title','meta_description','image_alt']));
                $snapshot['detail_heading']??=null;
                foreach(['highlights','faq_items'] as $key){if(isset($snapshot[$key])&&is_string($snapshot[$key])){$snapshot[$key]=json_decode($snapshot[$key],true,512,JSON_THROW_ON_ERROR);}}
                $translation->fill([...$snapshot,'ready'=>$essential])->save();
                if($essential){$page->save();}else{$page->update(['published'=>false,...($page->kind==='policy'?['approved_at'=>null]:[])]);}
            },3);}),
        ])->defaultSort('created_at','desc');
    }
    public static function getPages(): array{return ['index'=>\App\Filament\Resources\ContentRevisionResource\Pages\ListContentRevisions::route('/')];}
}
