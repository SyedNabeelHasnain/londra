<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Page;
use Illuminate\Http\Request;
final class PublicFormRecovery
{
    public function url(Request $request,string $locale): ?string
    {
        if(!in_array($locale,['en','it','es'],true)||!$request->isMethod('POST')){return null;}
        if($request->is('*/privacy-requests')){$key='privacy-request';}
        elseif($request->is('*/inquiries')){$key=$request->input('type')==='contact'?'contact':'enquiry';}
        else{return null;}
        try{
            $page=Page::with('translations')->where('key',$key)->where('published',true)->first();
            $translation=$page?->translation($locale);
            return $translation?->ready&&$translation->hasReviewContent()?$translation->url():null;
        }catch(\Throwable){return null;}
    }
}
