<?php
declare(strict_types=1);
namespace App\Filament\Resources;
use App\Models\{Page,Redirect,PageTranslation};
use App\Services\ContentPathPolicy;
use Filament\Actions\{EditAction,DeleteAction};
use Filament\Forms\Components\{TextInput,Select};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Unique;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
final class RedirectResource extends Resource
{
    protected static ?string $model=Redirect::class;
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-arrow-uturn-right';
    protected static string|\UnitEnum|null $navigationGroup='Content';
    protected static ?string $navigationLabel='URL redirects';
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('locale')->label('Language')->options(['en'=>'English','it'=>'Italiano','es'=>'Español'])->required(),
            TextInput::make('from_path')->label('Old URL path, without the language prefix')->required()->maxLength(190)->regex('~^[a-z0-9]+(?:[-/][a-z0-9]+)*$~D')->helperText('Use an unused local path. Current page paths, the homepage, form-token and verification paths cannot be redirected.')
                ->unique(table:Redirect::class,column:'from_path',ignoreRecord:true,modifyRuleUsing:fn(Unique $rule,Get $get)=>$rule->where('locale',$get('locale')))
                ->rules([fn(Get $get)=>function(string $attribute,mixed $value,\Closure $fail)use($get):void{
                    if(!is_string($value)||!ContentPathPolicy::isPublicPath($value)){$fail('Form-token and verification paths are reserved.');return;}
                    if(PageTranslation::query()->where('locale',$get('locale'))->where('slug',$value)->exists()){$fail('A published or draft page already owns this path in the selected language.');}
                }]),
            Select::make('page_id')->label('Destination page')->options(fn()=>Page::orderBy('key')->pluck('key','id'))->searchable()->required()->helperText('The destination must have a translation in the selected language. Public redirects follow the destination’s publication state.'),
        ])->columns(2);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('locale')->sortable(),TextColumn::make('from_path')->searchable(),TextColumn::make('page.key')->label('Destination')->searchable(),TextColumn::make('updated_at')->dateTime('d M Y H:i')->timezone('Europe/London')->sortable()])->recordActions([EditAction::make(),DeleteAction::make()->modalDescription('Remove this redirect. The old URL will no longer forward to the selected page.')])->defaultSort('updated_at','desc');
    }
    public static function getPages(): array { return ['index'=>\App\Filament\Resources\RedirectResource\Pages\ListRedirects::route('/'),'create'=>\App\Filament\Resources\RedirectResource\Pages\CreateRedirect::route('/create'),'edit'=>\App\Filament\Resources\RedirectResource\Pages\EditRedirect::route('/{record}/edit')]; }
}
