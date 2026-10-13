<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{MediaAsset,Page,Setting};
final class PublicContentUpdater
{
    public function run(): void
    {
        $previous = [];
        foreach (['previous-public-copy.json','previous-public-copy-2026-10-12.2.json','previous-public-copy-2026-10-13.1.json','previous-public-copy-2026-10-13.2.json','previous-public-copy-2026-10-13.3.json'] as $file) {
            $previous[] = json_decode((string) file_get_contents(database_path('content/'.$file)), true, 512, JSON_THROW_ON_ERROR);
        }
        $current = json_decode((string) file_get_contents(database_path('content/pages.json')), true, 512, JSON_THROW_ON_ERROR);
        $oldPages = array_map(static fn($snapshot) => array_column($snapshot['pages'], null, 'key'), $previous);
        $fields = ['title','eyebrow','intro','body','detail_heading','highlights','faq_items','seo_title','meta_description','image_alt'];
        foreach ($current as $record) {
            $old = array_values(array_filter(array_map(static fn($pages) => $pages[$record['key']] ?? null, $oldPages)));
            $page = Page::with('translations')->where('key', $record['key'])->lockForUpdate()->firstOrFail();
            $pageChanges = [];
            if ($page->image_key !== $record['image_key'] && in_array($page->image_key, array_column($old,'image_key'), true)) { $pageChanges['image_key'] = $record['image_key']; }
            if ((int) $page->sort_order !== $record['sort_order'] && in_array((int) $page->sort_order, array_column($old,'sort_order'), true)) { $pageChanges['sort_order'] = $record['sort_order']; }
            if ($pageChanges) { $page->update($pageChanges); }
            foreach ($record['translations'] as $locale => $translation) {
                $stored = $page->translations->firstWhere('locale', $locale);
                if (!$stored) { continue; }
                $changes = [];
                foreach ($fields as $field) {
                    $value = $stored->getAttribute($field);
                    $oldValues = array_map(static fn($page) => $page['translations'][$locale][$field] ?? null, $old);
                    $proposed = $translation[$field] ?? null;
                    if ($value === $proposed) { continue; }
                    if (in_array($value, $oldValues, true) || ($field==='body' && trim((string) $value)==='') || $this->containsInternalCopy($value)) {
                        $changes[$field] = $proposed;
                    }
                }
                if ($changes) {
                    if ($page->kind === 'policy') { $changes['ready'] = false; }
                    $stored->update($changes);
                }
            }
        }
        $alts = json_decode((string) file_get_contents(database_path('content/media-alt.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($alts as $key => $translations) {
            $media = MediaAsset::where('key', $key)->lockForUpdate()->first();
            if (!$media || $media->upload_path) { continue; }
            $changes = [];
            foreach ($translations as $locale => $value) {
                $field = 'alt_'.$locale;
                $oldValues = array_filter(array_map(static fn($snapshot) => $snapshot['media_alt'][$key][$locale] ?? null, $previous), 'is_string');
                if ($media->{$field} !== $value && (in_array($media->{$field}, $oldValues, true) || $this->containsInternalCopy($media->{$field}))) { $changes[$field] = $value; }
            }
            if ($changes) { $media->update($changes); }
        }
        $setting = Setting::find('interface-copy');
        if (!$setting) { return; }
        $saved = array_intersect_key(is_array($setting->value)?$setting->value:[],array_flip(['en','it','es']));
        foreach (['en','it','es'] as $locale) {
            $defaults = json_decode((string) file_get_contents(lang_path($locale.'/ui.json')), true, 512, JSON_THROW_ON_ERROR);
            if(!is_array($saved[$locale]??null)){unset($saved[$locale]);continue;}
            foreach ($saved[$locale] as $key => $value) {
                if (!array_key_exists($key, $defaults)||!is_string($value)||trim($value)==='') { unset($saved[$locale][$key]); continue; }
                $oldValues = array_map(static fn($snapshot) => $snapshot['ui'][$locale][$key] ?? null, $previous);
                if (in_array($value, $oldValues, true) || $this->containsInternalCopy($value)) {
                    $saved[$locale][$key] = $defaults[$key];
                }
            }
        }
        $setting->update(['value' => $saved]);
    }
    private function containsInternalCopy(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) { if ($this->containsInternalCopy($item)) { return true; } }
            return false;
        }
        return is_string($value) && preg_match('/lorem ipsum|\bdummy\b|\bplaceholder\b|\btemplate\b|development preview|design preview|anteprima (?:di sviluppo|del design)|vista previa (?:de desarrollo|del diseño)|before this notice is approved|prima dell.approvazione dell.informativa|antes de aprobar este aviso/iu', $value) === 1;
    }
}
