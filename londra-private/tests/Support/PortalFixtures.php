<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Contracts\EmailTransport;
use App\Models\{Inquiry,Page,Setting,User};
use App\Services\SmtpHealth;
use Illuminate\Support\Str;
trait PortalFixtures
{
    protected function syntheticAdministrator(): User
    {
        return User::create(['name'=>'Synthetic Administrator','email'=>'admin@example.test','password'=>Str::random(32)]);
    }
    protected function syntheticInquiry(array $extra=[]): Inquiry
    {
        return Inquiry::create($extra + ['reference'=>'LT-'.Str::ulid(),'submission_key'=>bin2hex(random_bytes(32)),'payload_fingerprint'=>bin2hex(random_bytes(32)),'type'=>'contact','locale'=>'en','status'=>'new','name'=>'Synthetic Guest','email'=>'guest@example.test','email_lookup'=>hash_hmac('sha256','guest@example.test',config('app.key')),'phone'=>'+440000000000','privacy_notice_at'=>now(),'privacy_notice_version'=>'synthetic']);
    }
    protected function approveSyntheticPolicies(): void
    {
        foreach(Page::whereIn('key',['privacy','terms','refund','cookies','accessibility'])->with('translations')->get() as $page){
            foreach($page->translations as $translation){$translation->update(['ready'=>true]);}
            $page->update(['approved_at'=>now(),'published'=>true]);
        }
    }
    protected function fakeEmailTransport(): object
    {
        $transport=new class implements EmailTransport {
            public array $sent=[];
            public bool $fail=false;
            public function send(string $recipient,string $subject,string $html,string $plain,?string $replyTo=null): void
            {
                if($this->fail){throw new \RuntimeException('Synthetic transport failure.');}
                $this->sent[]=compact('recipient','subject','html','plain','replyTo');
            }
        };
        app()->instance(EmailTransport::class,$transport);
        return $transport;
    }
    protected function approveSyntheticOperation(): object
    {
        $this->approveSyntheticPolicies();
        Setting::updateOrCreate(['key'=>'privacy-operations'],['value'=>['retention_days'=>180,'retention_enabled'=>true,'retention_approved'=>true,'mailbox_process_confirmed'=>true,'backup_process_confirmed'=>true,'mailbox_process'=>'Synthetic mailbox cleanup procedure.','backup_process'=>'Synthetic backup suppression procedure.']]);
        config(['londra.preview'=>false,'londra.accept_inquiries'=>true,'londra.publication_approved'=>true,'londra.privacy_approved'=>true,'londra.send_notifications'=>true]);
        $this->recordSyntheticSmtpAcceptance();
        return $this->fakeEmailTransport();
    }
    protected function recordSyntheticSmtpAcceptance(): void
    {
        Setting::updateOrCreate(['key'=>'smtp-health'],['value'=>['configuration_fingerprint'=>app(SmtpHealth::class)->fingerprint(),'smtp_accepted_at'=>now()->toIso8601String()]]);
    }
    protected function xpath(string $html): \DOMXPath
    {
        $document=new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NOERROR|LIBXML_NOWARNING);
        return new \DOMXPath($document);
    }
}
