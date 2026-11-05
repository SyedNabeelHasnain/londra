<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\{LocalePolicy,PolicyFingerprint};
use App\Models\{Page,PageTranslation,Setting};
/**
 * Applies the owner's recorded approval of the legal pages once. A policy is approved and published only when every
 * translation still matches the reviewed wording; edited policies keep their current state and are approved in Pages.
 */
final class PolicyApproval
{
    public function apply(): array
    {
        $record = json_decode((string) file_get_contents(database_path('content/policy-approval.json')), true, 16, JSON_THROW_ON_ERROR);
        $settingKey = 'policy-approval-'.$record['id'];
        if (Setting::find($settingKey)) { return []; }
        $approved = [];
        foreach ($record['pages'] as $key => $fingerprints) {
            $page = Page::with('translations')->where('key', $key)->where('kind', 'policy')->lockForUpdate()->first();
            if (!$page || $page->translations->count() !== count(LocalePolicy::LOCALES)) { continue; }
            $matches = $page->translations->every(fn(PageTranslation $translation): bool => isset($fingerprints[$translation->locale]) && hash_equals($fingerprints[$translation->locale], PolicyFingerprint::of($translation->only(PolicyFingerprint::FIELDS))));
            if (!$matches) { continue; }
            if ($page->published && $page->approved_at && $page->translations->every(fn(PageTranslation $translation): bool => $translation->ready)) { continue; }
            foreach ($page->translations as $translation) { if (!$translation->ready) { $translation->update(['ready' => true]); } }
            $page->refresh();
            $page->update(['approved_at' => now(), 'published' => true]);
            $approved[] = $key;
        }
        Setting::create(['key' => $settingKey, 'value' => ['approved_on' => $record['approved_on'], 'applied_at' => now()->toIso8601String(), 'published' => $approved]]);
        return $approved;
    }
}
