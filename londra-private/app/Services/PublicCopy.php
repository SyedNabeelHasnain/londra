<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Setting;
final class PublicCopy
{
    public function forLocale(string $locale): array
    {
        if(!in_array($locale,['en','it','es'],true)){throw new \InvalidArgumentException('Unsupported language.');}
        $defaults=$this->defaults($locale);
        $stored=Setting::find('interface-copy')?->value??[];
        $saved=is_array($stored)&&is_array($stored[$locale]??null)?$stored[$locale]:[];
        foreach($saved as $key=>$value){if(!array_key_exists($key,$defaults)||!is_string($value)||trim($value)===''){unset($saved[$key]);}}
        return $saved+$defaults;
    }
    public function recovery(string $locale): array
    {
        $defaults=$this->defaults($locale);
        try{return $this->forLocale($locale);}catch(\Throwable){return $defaults;}
    }
    public function applyLocale(string $locale): void
    {
        $lines=[];
        foreach($this->forLocale($locale) as $key=>$value){$lines['ui.'.$key]=$value;}
        \Illuminate\Support\Facades\Lang::addLines($lines,$locale);
    }
    private function defaults(string $locale): array
    {
        if(!in_array($locale,['en','it','es'],true)){throw new \InvalidArgumentException('Unsupported language.');}
        return json_decode((string) file_get_contents(lang_path($locale.'/ui.json')),true,512,JSON_THROW_ON_ERROR);
    }
}
