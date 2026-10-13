<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{Page,User,Setting,MediaAsset};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;
final class ReadinessReport
{
    private function hasPublicAssets(): bool
    {
        $manifest = public_path('build/manifest.json');
        if (!is_file($manifest)) { return false; }
        try {
            $entries = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            foreach (['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'] as $key) {
                if (!isset($entries[$key]['file']) || !is_string($entries[$key]['file'])) { return false; }
                if (!is_file(public_path('build/'.$entries[$key]['file']))) { return false; }
            }
            return true;
        } catch (\Throwable) { return false; }
    }
    public function checks(): array
    {
        $extensions=['ctype','curl','dom','exif','fileinfo','filter','gd','hash','intl','mbstring','openssl','pcre','pdo_mysql','session','tokenizer','xml','xmlwriter'];
        $missing=array_values(array_filter($extensions,fn(string $extension)=>!extension_loaded($extension)));
        $connection=false;
        try { $connection=(int)DB::select('SELECT 1 AS healthy')[0]->healthy===1; } catch (\Throwable) {}
        $pages=$connection ? Page::with('translations')->get() : collect();
        $unfinished=$pages->filter(fn(Page $page)=>$page->published && !\App\Domain\ReleasePolicy::canPublish($page->approval_required,$page->approved_at!==null,$page->reviewedLocales()))->count();
        $policies=$pages->whereIn('key',['privacy','terms','refund','cookies','accessibility'])->filter(fn(Page $page)=>$page->published&&$page->approved_at&&\App\Domain\ReleasePolicy::canPublish(true,true,$page->reviewedLocales()))->count();
        $cron = $connection ? Setting::find('cron-health')?->value : null;
        $cronRecent = false;
        try { $last = isset($cron['last_success_at']) ? CarbonImmutable::parse($cron['last_success_at']) : null; $cronRecent = $last && $last->betweenIncluded(now()->subMinutes(10),now()); } catch (\Throwable) {}
        $privacySchema = $connection && Schema::hasTable('data_requests') && Schema::hasTable('privacy_deliveries') && Schema::hasColumn('inquiries', 'active_until');
        $checks = [
            ['label'=>'PHP 8.5 compatibility','ok'=>PHP_VERSION_ID>=80504&&PHP_VERSION_ID<80600,'detail'=>PHP_VERSION],
            ['label'=>'Required PHP extensions','ok'=>$missing===[],'detail'=>$missing?implode(', ',$missing):'Available'],
            ['label'=>'MySQL connection','ok'=>$connection,'detail'=>$connection?'Query succeeded':'Not verified'],
            ['label'=>'Database job queue','ok'=>$connection&&config('queue.connections.database.driver')==='database'&&config('queue.connections.database.connection')==='mysql'&&config('queue.connections.database.table')==='jobs'&&Schema::connection('mysql')->hasTable('jobs'),'detail'=>'Application intake, retries and cron workers explicitly use the database connection and jobs table.'],
            ['label'=>'Privacy workflow database migration','ok'=>$privacySchema,'detail'=>$privacySchema?'Installed':'Apply the application update.'],
            ['label'=>'Background work ran recently','ok'=>$cronRecent,'detail'=>isset($cron['last_success_at']) ? 'Last success: '.$cron['last_success_at'].'; CLI PHP '.($cron['php_version']??'not recorded') : 'Configure the PHP 8.5 Cron Job.'],
            ['label'=>'Local country database included','ok'=>is_file(storage_path('app/geoip/country.mmdb')),'detail'=>'Country lookup is optional and requires an accurate privacy review.'],
            ['label'=>'Exactly one administrator','ok'=>$connection&&User::count()===1,'detail'=>'Single administrator only; registration is disabled.'],
            ['label'=>'Published pages have three reviewed translations','ok'=>$connection&&$pages->isNotEmpty()&&$unfinished===0,'detail'=>$unfinished.' published pages require attention.'],
            ['label'=>'Policies reviewed and published','ok'=>$policies===5,'detail'=>$policies.' of 5 policy pages approved and published.'],
            ['label'=>'Publication explicitly approved','ok'=>(bool)config('londra.publication_approved'),'detail'=>'Business approval is separate from software testing.'],
            ['label'=>'Privacy explicitly approved','ok'=>(bool)config('londra.privacy_approved'),'detail'=>'Requires accurate retention and processing information.'],
            ['label'=>'Responsive upload image processing','ok'=>app(MediaDerivative::class)->available(),'detail'=>'GD WebP encoding and EXIF functions must be available. Uploads are bounded and their output files verified.'],
            ['label'=>'Public stylesheet and script files staged','ok'=>$this->hasPublicAssets(),'detail'=>'Public and administrator assets are built and included.'],
            ['label'=>'Release content update applied','ok'=>$connection&&Setting::find('release-'.ReleaseUpdater::VERSION)!==null,'detail'=>'Release '.ReleaseUpdater::VERSION.'. Apply Administration > Application update after replacing the files. This preserves private configuration and individual content edits.'],
            ['label'=>'Debug information disabled','ok'=>!config('app.debug'),'detail'=>'Never expose production exception details.'],
        ];
        if (!$connection) { return $checks; }
        $state=app(PublicationState::class);$retention=app(PrivacyConfiguration::class)->retentionEnabled();$mail=$state->mailReady();
        $inquiryReasons=[];
        if(!config('londra.accept_inquiries')){$inquiryReasons[]='Public enquiry collection is disabled.';}
        if(!config('londra.publication_approved')){$inquiryReasons[]='Business publication approval is outstanding.';}
        if(!config('londra.privacy_approved')){$inquiryReasons[]='Privacy approval is outstanding.';}
        if(!$state->essentialPagesReady()){$inquiryReasons[]='Home, Contact and Enquiry must be published with three complete reviewed translations.';}
        if(!$state->policiesReady()){$inquiryReasons[]='Five reviewed policies are not all published in three languages.';}
        if(!$mail){$inquiryReasons[]='Notifications or the current SMTP acceptance check are not ready.';}
        if(!$retention){$inquiryReasons[]='The approved retention workflow is not enabled.';}
        $indexReasons=[];
        if(!config('londra.indexing_enabled')){$indexReasons[]='Search indexing is disabled.';}
        if(!config('londra.publication_approved')){$indexReasons[]='Business publication approval is outstanding.';}
        if(!$state->policiesReady()){$indexReasons[]='Five reviewed policies are not all published in three languages.';}
        $privacyReasons=[];
        if(!config('londra.privacy_approved')){$privacyReasons[]='Privacy approval is outstanding.';}
        if(!$state->privacyPageReady()){$privacyReasons[]='The privacy request page must be published with three complete reviewed translations.';}
        if(!$mail){$privacyReasons[]='Notifications or the current SMTP acceptance check are not ready.';}
        if(!$state->policiesReady()){$privacyReasons[]='Five reviewed policies are not all published in three languages.';}
        if(!$privacySchema){$privacyReasons[]='The privacy workflow migration is outstanding.';}
        $invalidMedia=MediaAsset::all()->filter(fn(MediaAsset $asset)=>!app(MediaReferences::class)->assetAvailable($asset))->count();
        $invalidSharedMedia=count(array_filter(app(MediaReferences::class)->globalKeys(),fn(string $key)=>!app(MediaReferences::class)->isAvailable($key)));
        $invalidPageMedia=$pages->where('published',true)->filter(fn(Page $page)=>(app(MediaReferences::class)->requiredForKind($page->kind)||$page->image_key)&&!app(MediaReferences::class)->isAvailable($page->image_key))->count();
        $metrics=app(DashboardMetrics::class)->summary();
        return [...$checks,
            ['label'=>'Required contact and receipt pages','ok'=>$state->essentialPagesReady(),'detail'=>$state->essentialPagesReady()?'Home, Contact and Enquiry are published with three complete reviewed translations.':'Restore and review the required Home, Contact and Enquiry pages before enabling intake.'],
            ['label'=>'Privacy request page','ok'=>$state->privacyPageReady(),'detail'=>$state->privacyPageReady()?'The privacy request page is published with three complete reviewed translations.':'Publish and review the privacy request page before enabling its intake.'],
            ['label'=>'Effective public enquiry collection','ok'=>$state->inquiriesAllowed(),'detail'=>$state->inquiriesAllowed()?'Name, email and phone intake is enabled.':'Forms are displayed with submission disabled; telephone, email and WhatsApp remain available. '.implode(' ',$inquiryReasons)],
            ['label'=>'Effective search indexing','ok'=>$state->indexingAllowed(),'detail'=>$state->indexingAllowed()?'Published pages permit indexing.':'Pages request no indexing. '.implode(' ',$indexReasons)],
            ['label'=>'Effective privacy request intake','ok'=>$state->privacyRequestsAllowed(),'detail'=>$state->privacyRequestsAllowed()?'Verified privacy request intake is enabled.':'Email contact remains available. '.implode(' ',$privacyReasons)],
            ['label'=>'Effective scheduled retention','ok'=>$retention,'detail'=>$retention?'The approved retention rule and external cleanup procedures are enabled.':'Review and enable the actual retention, mailbox and backup procedures in Privacy operations.'],
            ['label'=>'Effective notification processing','ok'=>$mail,'detail'=>$mail?'Notifications are enabled and the SMTP fingerprint matches a recorded acceptance test. Inbox arrival still requires operational confirmation.':'Enable notifications and record a successful test of the current Zoho SMTP settings.'],
            ['label'=>'Media and published page references','ok'=>$invalidMedia===0&&$invalidPageMedia===0&&$invalidSharedMedia===0,'detail'=>$invalidMedia.' media assets, '.$invalidPageMedia.' published page images and '.$invalidSharedMedia.' shared photograph references require attention.'],
            ['label'=>'Privacy deadlines','ok'=>$metrics['privacy_overdue']===0,'detail'=>$metrics['privacy_overdue'].' verified requests are overdue; '.$metrics['privacy_external_cleanup'].' require external cleanup review.'],
            ['label'=>'Failed enquiry and privacy mail','ok'=>$metrics['mail_failed']===0,'detail'=>'Enquiry failures: '.$metrics['enquiry_mail_failed'].'; privacy failures: '.$metrics['privacy_mail_failed'].'; awaiting processing: '.$metrics['mail_waiting'].'.'],
        ];
    }
}
