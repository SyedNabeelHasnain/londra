<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\ReleasePolicy;
use App\Models\Page;
final class PublicationState
{
    public function policiesReady(): bool
    {
        $pages=Page::query()->with('translations')->whereIn('key', ['privacy', 'terms', 'refund', 'cookies', 'accessibility'])->where('published', true)->whereNotNull('approved_at')->get();
        return $pages->count() === 5 && $pages->every(fn(Page $page) => ReleasePolicy::canPublish(true,(bool) $page->approved_at,$page->reviewedLocales()));
    }
    public function essentialPagesReady(): bool { return $this->pagesReady(Page::requiredKeys()); }
    public function privacyPageReady(): bool { return $this->pagesReady(['privacy-request']); }
    private function pagesReady(array $keys): bool
    {
        $pages=Page::with('translations')->whereIn('key',$keys)->where('published',true)->get();
        return $pages->count()===count($keys)&&$pages->every(fn(Page $page)=>ReleasePolicy::canPublish(false,true,$page->reviewedLocales()));
    }
    public function inquiriesAllowed(): bool { return ReleasePolicy::inquiryAllowed(config('londra')) && $this->mailReady() && $this->policiesReady() && $this->essentialPagesReady() && app(PrivacyConfiguration::class)->retentionEnabled(); }
    public function privacyRequestsAllowed(): bool { return config('londra.privacy_approved') && $this->mailReady() && $this->policiesReady() && $this->privacyPageReady() && \Illuminate\Support\Facades\Schema::hasTable('data_requests'); }
    public function indexingAllowed(): bool { return ReleasePolicy::indexAllowed(config('londra')) && $this->policiesReady(); }
    public function mailReady(): bool { return (bool) config('londra.send_notifications') && app(SmtpHealth::class)->verified(); }
}
