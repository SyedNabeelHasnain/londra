<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Domain\LocalePolicy;
use App\Models\Page;
use App\Services\PublicContact;
use App\Services\PublicationState;
use Illuminate\Http\Request;
final class DiscoveryController
{
    private const INTRODUCTIONS = [
        'en' => 'Private transfers, chauffeur services and personalised tours in London and throughout the United Kingdom.',
        'it' => 'Trasferimenti privati, servizi con autista e tour personalizzati a Londra e in tutto il Regno Unito.',
        'es' => 'Traslados privados, servicios de chófer y tours personalizados en Londres y en todo el Reino Unido.',
    ];
    public function robots(PublicationState $state)
    {
        $body=$state->indexingAllowed()?"User-agent: *\nDisallow: /admin\nDisallow: /livewire\nDisallow: /language/\nDisallow: /en/form-tokens\nDisallow: /it/form-tokens\nDisallow: /es/form-tokens\nSitemap: ".rtrim(config('app.url'),'/')."/sitemap.xml\n":"User-agent: *\nDisallow: /\n";
        return response($body)->header('Content-Type','text/plain; charset=UTF-8');
    }
    public function sitemap(PublicationState $state)
    {
        $xml=new \XMLWriter();$xml->openMemory();$xml->startDocument('1.0','UTF-8');
        $xml->startElement('urlset');$xml->writeAttribute('xmlns','http://www.sitemaps.org/schemas/sitemap/0.9');$xml->writeAttribute('xmlns:xhtml','http://www.w3.org/1999/xhtml');
        if($state->indexingAllowed()){
            foreach(Page::published()->with('translations')->get() as $page){
                $translations=$page->translations->where('ready',true);
                foreach($translations as $translation){
                    $xml->startElement('url');$xml->writeElement('loc',$translation->url());$xml->writeElement('lastmod',$translation->updated_at->utc()->toAtomString());
                    foreach($translations as $alternate){$xml->startElement('xhtml:link');$xml->writeAttribute('rel','alternate');$xml->writeAttribute('hreflang',$alternate->locale);$xml->writeAttribute('href',$alternate->url());$xml->endElement();}
                    $english=$translations->firstWhere('locale','en');
                    if($english){$xml->startElement('xhtml:link');$xml->writeAttribute('rel','alternate');$xml->writeAttribute('hreflang','x-default');$xml->writeAttribute('href',$page->key==='home'?rtrim(config('app.url'),'/').'/':$english->url());$xml->endElement();}
                    $xml->endElement();
                }
            }
        }
        $xml->endElement();$xml->endDocument();
        return response($xml->outputMemory())->header('Content-Type','application/xml; charset=UTF-8');
    }
    public function llms(Request $request, PublicationState $state)
    {
        $locale=(string) ($request->route('locale') ?? 'en');
        abort_unless(in_array($locale, LocalePolicy::LOCALES, true), 404);
        if(!$state->indexingAllowed()){return response($this->introduction($locale))->header('Content-Type','text/plain; charset=UTF-8');}
        $body="# Londra Transfers\n\n> ".self::INTRODUCTIONS[$locale]."\n\n";
        foreach(Page::published()->with('translations')->orderBy('sort_order')->get() as $page){$t=$page->translation($locale);if($t?->ready){$body.='- ['.str_replace(['[',']'], '', $t->title).']('.$t->url().'): '.preg_replace('/\s+/u',' ',$t->intro)."\n";}}
        return response($body)->header('Content-Type','text/plain; charset=UTF-8');
    }
    public function llmsFull(PublicationState $state)
    {
        if(!$state->indexingAllowed()){
            $body="# Londra Transfers\n\n";
            foreach(LocalePolicy::LOCALES as $locale){$body.='## '.strtoupper($locale)."\n\n".self::INTRODUCTIONS[$locale]."\n\n";}
            $contact=app(PublicContact::class)->current();
            return response($body.$contact['email'].' · '.$contact['phone_display']."\n")->header('Content-Type','text/plain; charset=UTF-8');
        }
        $body="# Londra Transfers\n\n";
        $pages=Page::published()->with('translations')->orderBy('sort_order')->get();
        foreach(LocalePolicy::LOCALES as $locale){
            foreach($pages as $page){
                $t=$page->translation($locale);
                if(!$t?->ready){continue;}
                $body.='## '.strtoupper($locale).' / '.$t->title."\n\n".$t->url()."\n\n".$t->intro."\n\n";
                if(trim((string) $t->detail_heading)!==''){$body.='### '.$t->detail_heading."\n\n";}
                if(trim((string) $t->body)!==''){$body.=$t->body."\n\n";}
                foreach($t->highlights ?? [] as $highlight){$body.='- '.$highlight."\n";}
                if($t->highlights){$body.="\n";}
                foreach($t->faq_items ?? [] as $item){$body.='### '.$item['q']."\n\n".$item['a']."\n\n";}
            }
        }
        return response($body)->header('Content-Type','text/plain; charset=UTF-8');
    }
    private function introduction(string $locale): string
    {
        $contact=app(PublicContact::class)->current();
        return "# Londra Transfers\n\n".self::INTRODUCTIONS[$locale]."\n\n".$contact['email'].' · '.$contact['phone_display']."\n";
    }
}
