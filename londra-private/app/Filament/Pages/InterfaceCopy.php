<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Filament\Forms\Components\{Select,Textarea};
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
final class InterfaceCopy extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-language';
    protected static string|\UnitEnum|null $navigationGroup='Content';
    protected static ?string $navigationLabel='Interface translations';
    protected string $view='filament.pages.interface-copy';
    public ?array $data=[];
    public array $drafts=[];
    #[Locked]
    public string $loadedLocale='en';
    public function mount(): void {$this->drafts['en']=app(\App\Services\PublicCopy::class)->forLocale('en');$this->form->fill(['locale'=>'en','copy'=>$this->drafts['en']]);}
    public function switchLocale(string $locale): void
    {
        abort_unless(in_array($locale,['en','it','es'],true),422);
        $this->drafts[$this->loadedLocale]=$this->data['copy']??[];
        $this->loadedLocale=$locale;
        $this->drafts[$locale]??=app(\App\Services\PublicCopy::class)->forLocale($locale);
        $this->form->fill(['locale'=>$locale,'copy'=>$this->drafts[$locale]]);
    }
    public function form(Schema $schema): Schema
    {
        $sections=[];
        foreach(trans('ui',[],'en') as $key=>$value){
            $group=$this->groupFor($key);
            $field=Textarea::make('copy.'.$key)->label(Str::headline($key))->helperText('Fixed key: '.$key.(in_array($key,['explore_named','view_named_vehicle'],true)?'. Keep the required :title token exactly once.':''))->required()->maxLength(1200)->rows(mb_strlen($value)>100?3:2);
            if(in_array($key,['explore_named','view_named_vehicle'],true)){$field->rules([fn()=>function(string $attribute,mixed $value,\Closure $fail):void{if(!is_string($value)||substr_count($value,':title')!==1){$fail('Keep the required :title token exactly once.');}}]);}
            $sections[$group][]=$field;
        }
        $components=[Select::make('locale')->options(['en'=>'English','it'=>'Italiano','es'=>'Español'])->required()->live()->afterStateUpdated(fn(string $state)=>$this->switchLocale($state))];
        foreach($sections as $title=>$fields){$components[]=Section::make($title)->schema($fields)->columns(2)->collapsible()->collapsed();}
        return $schema->components($components)->statePath('data');
    }
    public function save(): void
    {
        $data=$this->form->getState();$locale=$data['locale'];
        abort_unless(in_array($locale,['en','it','es'],true),422);
        abort_unless($locale===$this->loadedLocale,422);
        $defaults=trans('ui',[],$locale);$copy=array_intersect_key($data['copy'],$defaults);
        foreach($defaults as $key=>$value){if(!isset($copy[$key])||!is_string($copy[$key])||trim($copy[$key])===''||mb_strlen($copy[$key])>1200){throw \Illuminate\Validation\ValidationException::withMessages(['data.copy'=>'Every fixed key requires a non-empty translation, up to 1,200 characters.']);}}
        foreach(['explore_named','view_named_vehicle'] as $key){if(isset($defaults[$key])&&substr_count($copy[$key],':title')!==1){throw \Illuminate\Validation\ValidationException::withMessages(['data.copy.'.$key=>'Keep the required :title token exactly once.']);}}
        DB::transaction(function()use($locale,$copy):void{$setting=Setting::query()->lockForUpdate()->firstOrCreate(['key'=>'interface-copy'],['value'=>[]]);$saved=$setting->value??[];$saved[$locale]=$copy;$setting->update(['value'=>$saved]);});
        $this->drafts[$locale]=$copy;
        Notification::make()->title('Interface translations saved')->body('The selected language was saved. Drafts in the other languages remain available on this screen.')->success()->send();
    }
    private function groupFor(string $key): string
    {
        if(preg_match('/^(privacy_|erase_|data_)/',$key)){return 'Privacy requests';}
        if(preg_match('/^(error_|expired_|retry_|validation_|required_|invalid_)/',$key)){return 'Validation and recovery';}
        if(preg_match('/^(fleet_|vehicle_|luggage_|gallery_|compare_|passengers$|previous_vehicle$|next_vehicle$|view_vehicle$)/',$key)){return 'Fleet and photographs';}
        if(preg_match('/^(form_|contact_|enquir|offline_|process_|name$|email$|phone$|send$|sending$|received$|reference$|try_again$)/',$key)){return 'Contact and enquiry';}
        if(preg_match('/^(hero_|services_|airport_|experience_|value_|comfort_|punctuality_|discretion_|journey_|planning_|signature$|slogan$)/',$key)){return 'Homepage and service presentation';}
        if(preg_match('/^(nav_|menu_|footer_|home$|services$|fleet$|airports$|tours$|about$|contact$|directory$|faq|breadcrumb|reading_|back_|language$|light_|dark_)/',$key)){return 'Navigation and appearance';}
        return 'General interface';
    }
}
