<?php
declare(strict_types=1);
namespace App\Presentation;
use App\Services\ReleaseUpdater;
final class SiteViewModel
{
    public static function build(array $catalog, array $vehicles, array $media, array $ui, string $locale, string $key, bool $offline = false): array
    {
        $origin = 'https://londratransfers.co.uk'; $links=[]; $local=[]; $raw=[];
        foreach ($catalog as $page) {
            if (($page['approval_required'] ?? false) && empty($page['published'])) { continue; }
            $translation=$page['translations'][$locale] ?? null;
            if (!$translation || !$translation['ready']) { continue; }
            $links[$page['key']]=$offline ? $locale.'--'.$page['key'].'.html' : '/'.$locale.($translation['slug']!=='' ? '/'.$translation['slug'] : '');
            $local[$page['key']]=[...$translation,'key'=>$page['key'],'kind'=>$page['kind'],'image_key'=>$page['image_key'] ?? null,'url'=>$links[$page['key']]];
            $raw[$page['key']]=$page;
        }
        if (!isset($local[$key])) { throw new \InvalidArgumentException('The requested page is not publishable.'); }
        $alternates=[]; $choices=[];
        foreach (['en','it','es'] as $language) {
            $translation=$raw[$key]['translations'][$language] ?? null;
            if ($translation && $translation['ready']) {
                $alternates[$language]=$origin.'/'.$language.($translation['slug'] ? '/'.$translation['slug'] : '');
                $choices[$language]=$offline ? $language.'--'.$key.'.html' : '/language/'.$language.'/'.$key;
            }
        }
        $page=$local[$key]; $assetBase=$offline ? '' : '/';
        $v=['locale'=>$locale,'u'=>$ui,'page'=>$page,'catalog'=>$local,'links'=>$links,'alternates'=>$alternates,'language_choices'=>$choices,'vehicles'=>$vehicles,'media'=>$media,'asset_base'=>$assetBase,'origin'=>$origin,'canonical'=>$alternates[$locale],'offline'=>$offline,'indexable'=>false,'accept_inquiries'=>false,'csrf'=>'','form_token'=>'','submission_id'=>'00000000-0000-4000-8000-000000000000','form_action'=>$offline ? '#' : '/'.$locale.'/inquiries','contact'=>['phone_display'=>'+44 7438 700287','phone_link'=>'+447438700287','email'=>'info@londratransfers.co.uk','instagram'=>'londra_transfers_ltd'],'receipt'=>null,'errors'=>[],'old'=>[]];
        $v['template_root']=dirname(__DIR__, 2).'/resources/views/atelier';
        $v['asset_version']=ReleaseUpdater::VERSION;
        $v['privacy_requests_allowed']=false;
        $v['privacy_form_token']='';
        $v['privacy_receipt']=null;
        $v['schema']=self::schema($v);
        return $v;
    }
    public static function schema(array $v): string
    {
        $origin=$v['origin']; $page=$v['page'];
        $graph=[['@type'=>'Organization','@id'=>$origin.'/#organization','name'=>'Londra Transfers','url'=>$origin,'telephone'=>$v['contact']['phone_link'],'email'=>$v['contact']['email'],'logo'=>$origin.'/assets/artwork/logo-master-gold.png','areaServed'=>['@type'=>'Country','name'=>'United Kingdom'],'sameAs'=>['https://www.instagram.com/'.$v['contact']['instagram'].'/']],['@type'=>'WebSite','@id'=>$origin.'/#website','url'=>$origin,'name'=>'Londra Transfers','inLanguage'=>['en','it','es']],['@type'=>'WebPage','@id'=>$v['canonical'].'#webpage','url'=>$v['canonical'],'name'=>$page['seo_title'],'description'=>$page['meta_description'],'inLanguage'=>$v['locale'],'isPartOf'=>['@id'=>$origin.'/#website']]];
        if ($page['kind']==='service') { $graph[]=['@type'=>'Service','name'=>$page['title'],'description'=>$page['intro'],'url'=>$v['canonical'],'provider'=>['@id'=>$origin.'/#organization'],'areaServed'=>['@type'=>'Country','name'=>'United Kingdom']]; }
        if($page['key']!=='home') {
            $items=[];
            foreach (self::breadcrumbs($v) as $index=>$crumb) { $items[]=['@type'=>'ListItem','position'=>$index+1,'name'=>$crumb['title'],'item'=>$crumb['canonical']]; }
            $graph[]=['@type'=>'BreadcrumbList','itemListElement'=>$items];
        }
        return json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
    }
    public static function breadcrumbs(array $v): array
    {
        $origin=rtrim($v['origin'],'/');
        $crumbs=[['title'=>$v['u']['home'],'url'=>$v['links']['home'],'canonical'=>$origin.'/'.$v['locale']]];
        $parent=['service'=>'services','vehicle'=>'fleet','airport'=>'airports','tour'=>'tours'][$v['page']['kind']] ?? null;
        if($parent && isset($v['catalog'][$parent])) {
            $collection=$v['catalog'][$parent];
            $crumbs[]=['title'=>$collection['title'],'url'=>$collection['url'],'canonical'=>$origin.'/'.$v['locale'].($collection['slug']!==''?'/'.$collection['slug']:'')];
        }
        $crumbs[]=['title'=>$v['page']['title'],'url'=>null,'canonical'=>$v['canonical']];
        return $crumbs;
    }
}
