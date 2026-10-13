<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Setting;
final class PublicContact
{
    public function current(): array
    {
        $contact=$this->defaults();
        $stored=Setting::find('public-contact')?->value;
        if(!is_array($stored)){return $contact;}
        $display=$stored['phone_display']??null;$link=$stored['phone_link']??null;
        if(is_string($display)&&trim($display)!==''&&mb_strlen($display)<=40&&is_string($link)&&preg_match('/^\+[0-9]{6,20}$/D',$link)&&preg_replace('/\D/','',$display)===substr($link,1)){
            $contact['phone_display']=$display;$contact['phone_link']=$link;
        }
        $email=$stored['email']??null;
        if(is_string($email)&&strlen($email)<=254&&filter_var($email,FILTER_VALIDATE_EMAIL)){$contact['email']=$email;}
        $instagram=$stored['instagram']??null;
        if(is_string($instagram)&&preg_match('/^[a-zA-Z0-9_.]{1,60}$/D',$instagram)){$contact['instagram']=$instagram;}
        return $contact;
    }
    public function recovery(): array
    {
        try{return $this->current();}catch(\Throwable){return $this->defaults();}
    }
    private function defaults(): array
    {
        return ['phone_display'=>(string)config('londra.contact.phone'),'phone_link'=>(string)config('londra.contact.tel'),'email'=>(string)config('londra.contact.email'),'instagram'=>(string)config('londra.contact.instagram')];
    }
}
