<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\{Page,MediaAsset,PageTranslation,Redirect,Setting,Vehicle};
use App\Presentation\SiteViewModel;
use App\Services\{InquiryFormToken,PublicationState};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
final class PublicPageController
{
    public function show(Request $request, string $locale, string $slug = '')
    {
        $translation=PageTranslation::with('page')->where('locale',$locale)->where('slug',$slug)->where('ready',true)->whereHas('page',fn($q)=>$q->where('published',true))->first();
        if (!$translation) {
            $old=Redirect::with('page.translations')->where('locale',$locale)->where('from_path',$slug)->first();
            $target=$old?->page?->translation($locale);
            if($old?->page?->published && $target?->ready && $target->slug !== $slug) { return redirect($target->url(),301); }
            abort(404);
        }
        $catalog=Page::published()->with('translations')->orderBy('sort_order')->get()->map(fn(Page $page)=>['key'=>$page->key,'kind'=>$page->kind,'image_key'=>$page->image_key,'published'=>true,'approval_required'=>$page->approval_required,'translations'=>$page->translations->keyBy('locale')->toArray()])->all();
        $vehicles=Vehicle::with('page.translations')->where('active',true)->orderBy('sort_order')->get()->map(fn(Vehicle $vehicle)=>[...$vehicle->toArray(),'page_key'=>$vehicle->page->key])->all();
        $media=[];
        foreach(MediaAsset::all() as $asset){$media[$asset->key]=['original'=>$asset->public_path,'url'=>$asset->url(),'width'=>$asset->width,'height'=>$asset->height,'variants'=>$asset->variants ?? [],'alt'=>['en'=>$asset->alt_en,'it'=>$asset->alt_it,'es'=>$asset->alt_es]];}
        $v=SiteViewModel::build($catalog,$vehicles,$media,app(\App\Services\PublicCopy::class)->forLocale($locale),$locale,$translation->page->key);
        $state=app(PublicationState::class);
        $v['origin']=rtrim(config('app.url'),'/');
        foreach($v['alternates'] as $language=>&$url){$url=str_replace('https://londratransfers.co.uk',$v['origin'],$url);} unset($url);
        $v['canonical']=$v['alternates'][$locale];
        $v['contact']=app(\App\Services\PublicContact::class)->current();
        $v['indexable']=$state->indexingAllowed();$v['accept_inquiries']=$state->inquiriesAllowed();
        $v['csrf']=csrf_token();$v['submission_id']=(string) Str::uuid();
        $v['form_token']=app(InquiryFormToken::class)->issue($request->session()->getId(),$locale);
        $v['privacy_form_token']=app(InquiryFormToken::class)->issue($request->session()->getId(),$locale.':privacy');
        $v['privacy_requests_allowed']=$state->privacyRequestsAllowed();
        $v['privacy_receipt']=$request->session()->get('privacy_receipt');
        $v['receipt']=$request->session()->get('inquiry_receipt');
        $v['errors']=$request->session()->get('errors')?->getBag('default')->toArray() ?? [];
        $v['old']=$request->session()->getOldInput();
        $v['schema']=SiteViewModel::schema($v);
        $v['css_url']=app(\Illuminate\Foundation\Vite::class)->asset('resources/css/app.css');
        $v['js_url']=app(\Illuminate\Foundation\Vite::class)->asset('resources/js/app.js');
        if ($translation->page->kind === 'vehicle') {
            $matched = null;
            foreach ($vehicles as $candidate) {
                if (($candidate['page_key'] ?? '') === $translation->page->key) {
                    $matched = $candidate; break;
                }
            }
            $rights = Setting::find('fleet-photo-galleries')?->value ?? [];
            $v['fleet_gallery'] = $matched ? \App\Services\FleetPhotoGallery::visible((string)$matched['code'], (array)$rights, $v['media'], $locale) : [];
        }
        return view('site',['v'=>$v]);
    }
}
