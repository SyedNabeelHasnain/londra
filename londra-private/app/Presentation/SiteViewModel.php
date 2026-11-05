<?php
declare(strict_types=1);
namespace App\Presentation;
use App\Services\ReleaseUpdater;

final class SiteViewModel
{
    /** Page kinds that belong to a collection, mapped to the key of that collection page. */
    public const COLLECTIONS = ['service' => 'services', 'vehicle' => 'fleet', 'airport' => 'airports', 'tour' => 'tours'];
    /** Primary header destinations, in display order. */
    public const HEADER = ['services', 'airports', 'fleet', 'tours', 'about', 'contact'];
    /** Page kinds that do not repeat the closing invitation band. */
    public const WITHOUT_INVITATION = ['contact', 'enquiry', 'policy', 'privacy-request', 'faq', 'directory'];

    public static function build(array $catalog, array $vehicles, array $media, array $ui, string $locale, string $key, bool $offline = false): array
    {
        $origin = 'https://londratransfers.co.uk'; $links = []; $local = []; $raw = [];
        foreach ($catalog as $page) {
            if (($page['approval_required'] ?? false) && empty($page['published'])) { continue; }
            $translation = $page['translations'][$locale] ?? null;
            if (!$translation || !$translation['ready']) { continue; }
            $links[$page['key']] = $offline ? $locale.'--'.$page['key'].'.html' : '/'.$locale.($translation['slug'] !== '' ? '/'.$translation['slug'] : '');
            $local[$page['key']] = [...$translation, 'key' => $page['key'], 'kind' => $page['kind'], 'image_key' => $page['image_key'] ?? null, 'url' => $links[$page['key']]];
            $raw[$page['key']] = $page;
        }
        if (!isset($local[$key])) { throw new \InvalidArgumentException('The requested page is not publishable.'); }
        $alternates = []; $choices = [];
        foreach (['en', 'it', 'es'] as $language) {
            $translation = $raw[$key]['translations'][$language] ?? null;
            if ($translation && $translation['ready']) {
                $alternates[$language] = $origin.'/'.$language.($translation['slug'] ? '/'.$translation['slug'] : '');
                $choices[$language] = $offline ? $language.'--'.$key.'.html' : '/language/'.$language.'/'.$key;
            }
        }
        $page = $local[$key]; $assetBase = $offline ? '' : '/';
        $v = ['locale' => $locale, 'u' => $ui, 'page' => $page, 'catalog' => $local, 'links' => $links, 'alternates' => $alternates, 'language_choices' => $choices, 'vehicles' => $vehicles, 'media' => $media, 'asset_base' => $assetBase, 'origin' => $origin, 'canonical' => $alternates[$locale], 'offline' => $offline, 'indexable' => false, 'accept_inquiries' => false, 'csrf' => '', 'form_token' => '', 'submission_id' => '00000000-0000-4000-8000-000000000000', 'form_action' => $offline ? '#' : '/'.$locale.'/inquiries', 'contact' => ['phone_display' => '+44 7438 700287', 'phone_link' => '+447438700287', 'email' => 'info@londratransfers.co.uk', 'instagram' => 'londra_transfers_ltd'], 'receipt' => null, 'errors' => [], 'old' => []];
        $v['asset_version'] = ReleaseUpdater::VERSION;
        $v['privacy_requests_allowed'] = false;
        $v['privacy_form_token'] = '';
        $v['privacy_receipt'] = null;
        $v['schema'] = self::schema($v);
        return $v;
    }

    public static function schema(array $v): string
    {
        $origin = $v['origin']; $page = $v['page'];
        $graph = [['@type' => 'Organization', '@id' => $origin.'/#organization', 'name' => 'Londra Transfers', 'legalName' => 'Londra Transfers Limited', 'url' => $origin, 'telephone' => $v['contact']['phone_link'], 'email' => $v['contact']['email'], 'logo' => $origin.'/assets/artwork/logo-master-gold.png', 'areaServed' => ['@type' => 'Country', 'name' => 'United Kingdom'], 'sameAs' => ['https://www.instagram.com/'.$v['contact']['instagram'].'/']], ['@type' => 'WebSite', '@id' => $origin.'/#website', 'url' => $origin, 'name' => 'Londra Transfers', 'inLanguage' => ['en', 'it', 'es']], ['@type' => 'WebPage', '@id' => $v['canonical'].'#webpage', 'url' => $v['canonical'], 'name' => $page['seo_title'], 'description' => $page['meta_description'], 'inLanguage' => $v['locale'], 'isPartOf' => ['@id' => $origin.'/#website']]];
        if ($page['kind'] === 'service') { $graph[] = ['@type' => 'Service', 'name' => $page['title'], 'description' => $page['intro'], 'url' => $v['canonical'], 'provider' => ['@id' => $origin.'/#organization'], 'areaServed' => ['@type' => 'Country', 'name' => 'United Kingdom']]; }
        if ($page['kind'] === 'faq' && !empty($page['faq_items'])) {
            $graph[] = ['@type' => 'FAQPage', '@id' => $v['canonical'].'#faq', 'url' => $v['canonical'], 'inLanguage' => $v['locale'], 'mainEntity' => array_map(static fn(array $item) => ['@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']]], array_values($page['faq_items']))];
        }
        if ($page['key'] !== 'home') {
            $items = [];
            foreach (self::breadcrumbs($v) as $index => $crumb) { $items[] = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb['title'], 'item' => $crumb['canonical']]; }
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        return json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    public static function breadcrumbs(array $v): array
    {
        $origin = rtrim($v['origin'], '/');
        $crumbs = [['title' => $v['u']['home'], 'url' => $v['links']['home'], 'canonical' => $origin.'/'.$v['locale']]];
        $parent = self::COLLECTIONS[$v['page']['kind']] ?? null;
        if ($parent && isset($v['catalog'][$parent])) {
            $collection = $v['catalog'][$parent];
            $crumbs[] = ['title' => $collection['title'], 'url' => $collection['url'], 'canonical' => $origin.'/'.$v['locale'].($collection['slug'] !== '' ? '/'.$collection['slug'] : '')];
        }
        $crumbs[] = ['title' => $v['page']['title'], 'url' => null, 'canonical' => $v['canonical']];
        return $crumbs;
    }

    /** Vehicles whose detail page is published in the current language. */
    public static function fleet(array $v): array
    {
        return array_values(array_filter($v['vehicles'], static fn(array $vehicle) => isset($v['catalog'][$vehicle['page_key']])));
    }

    /** Published pages of one kind, in CMS order. */
    public static function ofKind(array $v, string $kind): array
    {
        return array_values(array_filter($v['catalog'], static fn(array $item) => $item['kind'] === $kind));
    }

    /**
     * The complete site navigation: every published page in the current language appears exactly once.
     * Vehicle pages appear only while their vehicle is active.
     */
    public static function navigation(array $v): array
    {
        $catalog = $v['catalog']; $u = $v['u'];
        $active = array_fill_keys(array_column($v['vehicles'], 'page_key'), true);
        $groups = [];
        foreach (self::COLLECTIONS as $kind => $collection) {
            $items = array_values(array_filter($catalog, static fn(array $item) => $item['kind'] === $kind && ($kind !== 'vehicle' || isset($active[$item['key']]))));
            if (!$items && !isset($catalog[$collection])) { continue; }
            $groups[] = ['key' => $kind, 'label' => $u[$collection], 'overview' => $catalog[$collection] ?? null, 'items' => $items];
        }
        $structural = ['home', 'service', 'vehicle', 'airport', 'tour', 'policy', 'privacy-request'];
        $company = array_values(array_filter($catalog, static fn(array $item) => !in_array($item['kind'], $structural, true) && !in_array($item['key'], self::COLLECTIONS, true)));
        if ($company) { $groups[] = ['key' => 'company', 'label' => $u['footer_company'], 'overview' => null, 'items' => $company]; }
        $legal = array_values(array_filter($catalog, static fn(array $item) => in_array($item['kind'], ['policy', 'privacy-request'], true)));
        if ($legal) { $groups[] = ['key' => 'policy', 'label' => $u['navigation_policies'], 'overview' => null, 'items' => $legal]; }
        return $groups;
    }

    /** The navigation group that contains the current page. */
    public static function currentGroup(array $v): ?string
    {
        foreach (self::navigation($v) as $group) {
            if (($group['overview']['key'] ?? null) === $v['page']['key']) { return $group['key']; }
            foreach ($group['items'] as $item) { if ($item['key'] === $v['page']['key']) { return $group['key']; } }
        }
        return null;
    }

    /** The collection group that a service, airport, vehicle or tour page belongs to, for the in-section list. */
    public static function sectionGroup(array $v): ?array
    {
        if (!isset(self::COLLECTIONS[$v['page']['kind']])) { return null; }
        foreach (self::navigation($v) as $group) {
            if ($group['key'] === $v['page']['kind'] && count($group['items']) > 1) { return $group; }
        }
        return null;
    }

    /** Figures shown in the homepage summary. Every value is derived from published records. */
    public static function facts(array $v): array
    {
        $fleet = self::fleet($v); $u = $v['u']; $facts = [];
        if ($fleet) {
            $facts[] = ['value' => (string) count($fleet), 'label' => $u['fact_fleet']];
            $facts[] = ['value' => (string) max(array_map(static fn(array $vehicle) => (int) $vehicle['passengers'], $fleet)), 'label' => $u['fact_guests']];
        }
        $airports = self::ofKind($v, 'airport');
        if ($airports) { $facts[] = ['value' => (string) count($airports), 'label' => $u['fact_airports']]; }
        return $facts;
    }

    public static function vehicleName(string $title): string
    {
        return str_replace('Mercedes-Benz ', '', $title);
    }
}
