<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Actions\PublishPage;
use App\Models\Page;
use App\Models\MediaAsset;
use App\Models\{PageTranslation,Redirect};
use App\Filament\Resources\PageResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\{TextInput,Textarea,Select,Repeater,Toggle,TagsInput};
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use App\Services\MediaReferences;
use App\Services\ContentPathPolicy;
use Illuminate\Validation\Rules\Unique;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn,IconColumn};
use Filament\Tables\Filters\SelectFilter;
final class PageResource extends Resource
{
    protected static ?string $model = Page::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return $record instanceof Page && $record->canBeDeleted(); }
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')->schema([
                TextInput::make('key')->required()->maxLength(80)->regex('/^[a-z0-9-]+$/D')->unique(ignoreRecord:true)->disabledOn('edit'),
                Select::make('kind')->required()->live()->disabledOn('edit')->options(fn(string $operation) => $operation === 'create' ? ['service'=>'Service','vehicle'=>'Vehicle','airport'=>'Airport','tour'=>'Tour','editorial'=>'Editorial','policy'=>'Policy'] : array_combine(['home','services','service','fleet','vehicle','airports','airport','tours','tour','editorial','contact','enquiry','faq','directory','policy','privacy-request'],['Home','Service overview','Service','Fleet overview','Vehicle','Airport overview','Airport','Tour overview','Tour','Editorial','Contact','Enquiry','FAQs','Directory','Policy','Data deletion request'])),
                Select::make('image_key')->label(fn(Get $get) => app(MediaReferences::class)->requiredForKind((string) $get('kind')) ? 'Main photograph and sharing image' : 'Sharing image')
                    ->helperText(fn(Get $get) => $get('kind')==='vehicle'?'Used on this vehicle detail page and for sharing. Fleet, gallery and comparison photography are set separately under Fleet vehicles.':(app(MediaReferences::class)->requiredForKind((string) $get('kind')) ? 'The main photograph is shown on the page. Choose an available image before publishing.' : 'Used for search and social sharing. This page layout does not show a main photograph.'))
                    ->options(fn()=>MediaAsset::orderBy('key')->pluck('key','key'))->required(fn(Get $get) => app(MediaReferences::class)->requiredForKind((string) $get('kind')))->searchable(),
                TextInput::make('sort_order')->numeric()->minValue(0)->maxValue(999)->default(0),
                TextInput::make('source_reference')->label('Source or approval reference')->maxLength(255),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->label('Language')->disabledOn('edit')->options(['en'=>'English','it'=>'Italiano','es'=>'Español'])->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('slug')->label('URL path, without the language prefix')->dehydrateStateUsing(fn($state)=>(string)($state??''))->maxLength(190)
                    ->unique(table:PageTranslation::class,column:'slug',ignoreRecord:true,modifyRuleUsing:fn(Unique $rule,Get $get)=>$rule->where('locale',$get('locale')))
                    ->rules([fn(Get $get,?PageTranslation $record)=>function(string $attribute,mixed $value,\Closure $fail)use($get,$record):void{
                        $path=(string)($value??'');
                        if(!ContentPathPolicy::isPublicPath($path)){ $fail('Use an unreserved local path with lowercase letters, numbers, hyphens and slashes.'); return; }
                        if(Redirect::query()->where('locale',$get('locale'))->where('from_path',$path)->when($record,fn($query)=>$query->where('page_id','!=',$record->page_id))->exists()){$fail('A redirect to another page already owns this path in the selected language.');}
                    }]),
                TextInput::make('title')->label(fn(Get $get) => $get('../../kind') === 'home' ? 'Homepage hero heading' : 'Page heading')->required()->maxLength(200), TextInput::make('eyebrow')->label('Short label above the heading')->maxLength(160),
                Textarea::make('intro')->required()->rows(3), Textarea::make('body')->label('Main text; blank lines separate paragraphs')->helperText(fn(Get $get)=>$get('../../kind')==='home'?'Optional supporting text below the homepage introduction.':(in_array($get('../../kind'),['services','fleet','airports','tours','faq','directory'],true)?'Supporting text displayed before this page’s collection.':'Text displayed in the main reading section. Detail and policy translations need this text before review.'))->rows(8),
                TextInput::make('detail_heading')->label('Heading above the main text')->maxLength(200)->visible(fn(Get $get) => in_array($get('../../kind'), ['service','vehicle','airport','tour','editorial'], true)),
                TagsInput::make('highlights')->label('Destination or feature list')->visible(fn(Get $get) => in_array($get('../../kind'), ['service','vehicle','airport','tour','editorial'], true)),
                Repeater::make('faq_items')->label('Questions and answers')->visible(fn(Get $get) => $get('../../kind') === 'faq')->schema([TextInput::make('q')->label('Question')->required()->maxLength(240), Textarea::make('a')->label('Answer')->required()->maxLength(4000)])->defaultItems(0)->collapsible()->columnSpanFull(),
                TextInput::make('seo_title')->required()->maxLength(200), Textarea::make('meta_description')->required()->rows(3),
                TextInput::make('image_alt')->label('Alternative text for the page and sharing image')->maxLength(255),
                Toggle::make('ready')->label('This translation has been reviewed')->disabled(fn(Get $get)=>in_array($get('../../key'),Page::requiredKeys(),true)&&Page::where('key',$get('../../key'))->where('published',true)->exists())->helperText('Published Home, Contact and Enquiry translations must remain reviewed so essential navigation and receipts stay available.')->default(false),
            ])->default([['locale'=>'en'],['locale'=>'it'],['locale'=>'es']])->minItems(3)->maxItems(3)->addable(false)->deletable(false)->reorderable(false)->columns(2)->columnSpanFull(),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('key')->searchable()->sortable(),TextColumn::make('kind')->sortable(),IconColumn::make('published')->boolean(),IconColumn::make('approval_required')->boolean(),TextColumn::make('updated_at')->dateTime('d M Y H:i')->timezone('Europe/London')->sortable()])
            ->filters([SelectFilter::make('kind')->options(['service'=>'Services','vehicle'=>'Fleet','airport'=>'Airports','tour'=>'Tours','policy'=>'Policies'])])
            ->recordActions([
                EditAction::make(),
                Action::make('public_page')->label('View published page')->icon('heroicon-o-arrow-top-right-on-square')->visible(fn(Page $record)=>$record->published&&$record->translations()->where('locale','en')->exists())->url(fn(Page $record)=>$record->translations()->where('locale','en')->first()?->url())->openUrlInNewTab(),
                Action::make('publish')->requiresConfirmation()->visible(fn(Page $record)=>!$record->published)->action(fn(Page $record)=>app(PublishPage::class)->handle($record)),
                Action::make('unpublish')->requiresConfirmation()->visible(fn(Page $record)=>$record->published&&!in_array($record->key,Page::requiredKeys(),true))->action(fn(Page $record)=>$record->update(['published'=>false])),
                Action::make('approve')->label('Approve reviewed policy')->requiresConfirmation()->modalDescription('Only approve after the business has supplied and reviewed the actual policy in all three languages.')->visible(fn(Page $record)=>$record->approval_required && !$record->approved_at)->action(fn(Page $record)=>$record->update(['approved_at'=>now()])),
                DeleteAction::make()->authorize(fn(Page $record)=>self::canDelete($record))->visible(fn(Page $record)=>self::canDelete($record))->modalDescription('Permanently delete this unpublished custom page and its translations and revisions. Required pages and referenced pages are retained.'),
            ])->defaultSort('sort_order');
    }
    public static function getPages(): array { return ['index'=>Pages\ListPages::route('/'),'create'=>Pages\CreatePage::route('/create'),'edit'=>Pages\EditPage::route('/{record}/edit')]; }
}
