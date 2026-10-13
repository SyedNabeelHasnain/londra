<?php
declare(strict_types=1);
namespace App\Filament\Pages;
use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
final class PortalSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    protected static string|\BackedEnum|null $navigationIcon='heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup='Administration';
    protected string $view='filament.pages.settings';
    public ?array $data=[];
    public function mount(): void
    {
        $stored=Setting::find('public-contact')?->value ?? [];
        $this->form->fill($stored+['phone_display'=>config('londra.contact.phone'),'phone_link'=>config('londra.contact.tel'),'email'=>config('londra.contact.email'),'instagram'=>config('londra.contact.instagram')]);
    }
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone_display')->label('Displayed telephone and WhatsApp number')->required()->maxLength(40)->helperText('Formatting is allowed, but its digits must match the dialling number.'),TextInput::make('phone_link')->label('International dialling number')->required()->regex('/^\+[0-9]{6,20}$/D'),
            TextInput::make('email')->label('Public contact email')->helperText('Changes visitor contact links and email signatures. The Zoho account and administrator notification address remain configured separately.')->required()->email()->maxLength(254),TextInput::make('instagram')->required()->regex('/^[a-zA-Z0-9_.]{1,60}$/D'),
        ])->statePath('data');
    }
    public function save(): void
    {
        $data=$this->form->getState();
        if(preg_replace('/\D/','',$data['phone_display'])!==preg_replace('/\D/','',$data['phone_link'])){throw \Illuminate\Validation\ValidationException::withMessages(['data.phone_display'=>'The displayed and dialling numbers must contain the same digits.']);}
        Setting::updateOrCreate(['key'=>'public-contact'],['value'=>$data]);
        Notification::make()->title('Contact information saved')->success()->send();
    }
}
