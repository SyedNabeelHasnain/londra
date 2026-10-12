<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class ImportPolicyDrafts extends Command
{
    protected $signature='londra:stage-policy-drafts {--path= : Absolute path to an unpublished policy-draft JSON bundle}';
    protected $description='Stage unapproved, unpublished multilingual policy drafts without overwriting existing content.';
    private const KEYS=['terms','refund','privacy','cookies','accessibility'];
    private const LOCALES=['en','it','es'];
    public function handle():int
    {
        $path=(string)($this->option('path') ?: database_path('content/policy_drafts.json'));
        if (!is_file($path)||!is_readable($path)) { $this->error('Policy draft file unavailable. Nothing changed.');return self::FAILURE; }
        try {
            $json=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
            if (!is_array($json)||!is_array($json['records']??null)||count($json['records'])!==5||!str_starts_with((string)($json['status']??''),'UNPUBLISHED_')) {throw new RuntimeException('Unrecognised policy-draft bundle.');}
            $byKey=[];
            foreach($json['records'] as $item){
                $key=(string)($item['key']??'');
                if(!in_array($key,self::KEYS,true)||isset($byKey[$key])||($item['approved']??true)!==false||($item['published']??true)!==false||($item['review_required']??false)!==true){throw new RuntimeException('Invalid policy status or duplicate key.');}
                $translations=$item['translations']??null;
                if(!is_array($translations)||count($translations)!==3){throw new RuntimeException('Each policy must have three languages.');}
                foreach(self::LOCALES as $locale){
                    $t=$translations[$locale]??null;
                    if(!is_array($t)||($t['ready']??true)!==false||($t['approved']??true)!==false||($t['locale']??'')!==$locale){throw new RuntimeException('Policy translation is not marked as an unapproved draft.');}
                    foreach(['slug','title','intro','body','seo_title','meta_description'] as $field){
                        if(!is_string($t[$field]??null)||trim($t[$field])===''){throw new RuntimeException('A required policy translation field is empty.');}
                    }
                    if(mb_strlen($t['body'])<350){throw new RuntimeException('Policy body is incomplete.');}
                }
                $byKey[$key]=$item;
            }
            if(count($byKey)!==count(self::KEYS)){throw new RuntimeException('Policy key coverage is incomplete.');}
            $imported=0;$skipped=0;
            DB::transaction(function()use($byKey,&$imported,&$skipped):void{
                foreach(self::KEYS as $key){
                    $page=Page::query()->with('translations')->where('key',$key)->lockForUpdate()->firstOrFail();
                    if($page->kind!=='policy'){throw new RuntimeException('Policy page identity mismatch.');}
                    if($page->published||$page->approved_at!==null||$page->translations->count()!==3||$page->translations->contains(fn($t)=>$t->ready||trim((string)$t->body)!=='')){$skipped++;continue;}
                    foreach(self::LOCALES as $locale){
                        $t=$page->translations->firstWhere('locale',$locale);
                        if(!$t){throw new RuntimeException('Missing existing policy language.');}
                        $draft=$byKey[$key]['translations'][$locale];
                        $t->fill(array_intersect_key($draft,array_fill_keys(['slug','title','intro','body','seo_title','meta_description'],true)));
                        $t->ready=false;
                        $t->save();
                    }
                    $imported++;
                }
            },3);
            $this->info($imported.' policy drafts staged for owner review; '.$skipped.' existing/approved policies preserved. Nothing was published.');
            return self::SUCCESS;
        }catch(\Throwable $e){$this->error('Policy import cancelled: '.$e->getMessage());return self::FAILURE;}
    }
}
