<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$keys=['terms','refund','privacy','cookies','accessibility'];
$locales=['en','it','es'];
\Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>'DatabaseSeeder','--force'=>true]);
$records=[];
foreach($keys as $key){
    $page=\App\Models\Page::with('translations')->where('key',$key)->firstOrFail();
    if($page->published||$page->approved_at!==null){throw new \RuntimeException('Unexpected published policy test state');}
    $ts=[];
    foreach($locales as $locale){
        $original=$page->translations->firstWhere('locale',$locale);
        if(!$original){throw new \RuntimeException('Missing seed locale');}
        $ts[$locale]=['locale'=>$locale,'slug'=>$original->slug,'title'=>'Synthetic policy '.$locale,'intro'=>'Synthetic content for automated test only.','body'=>str_repeat('Synthetic isolated test content for review. ',15),'seo_title'=>'Synthetic '.$locale,'meta_description'=>'Synthetic isolated test metadata.','ready'=>false,'approved'=>false];
    }
    $records[]=['key'=>$key,'approved'=>false,'published'=>false,'review_required'=>true,'translations'=>$ts];
}
$path=storage_path('app/qa21-policies-fixture.json');
if(!is_dir(dirname($path))){mkdir(dirname($path),0750,true);}
file_put_contents($path,json_encode(['status'=>'UNPUBLISHED_TEST_FIXTURE','records'=>$records],JSON_THROW_ON_ERROR));
try{
    $result=\Illuminate\Support\Facades\Artisan::call('londra:stage-policy-drafts',['--path'=>$path]);
    if($result!==0){throw new \RuntimeException('Initial guarded import failed: '.\Illuminate\Support\Facades\Artisan::output());}
    foreach($keys as $key){
        $page=\App\Models\Page::with('translations')->where('key',$key)->firstOrFail();
        if($page->approved_at!==null||$page->published||$page->translations->count()!==3){throw new \RuntimeException('Importer bypassed publication gate');}
        foreach($page->translations as $t){if($t->ready||!str_contains((string)$t->body,'Synthetic isolated test content')){throw new \RuntimeException('Draft not staged correctly');}}
    }
    $before=\App\Models\PageTranslation::whereHas('page',fn($q)=>$q->where('key','privacy'))->where('locale','en')->firstOrFail();
    $beforeBody=$before->body;
    $result=\Illuminate\Support\Facades\Artisan::call('londra:stage-policy-drafts',['--path'=>$path]);
    $after=$before->fresh();
    if($result!==0||$after->body!==$beforeBody||$after->ready){throw new \RuntimeException('Importer is not idempotent');}
    file_put_contents($path,json_encode(['status'=>'UNPUBLISHED_TEST_FIXTURE','records'=>array_slice($records,0,4)],JSON_THROW_ON_ERROR));
    if(\Illuminate\Support\Facades\Artisan::call('londra:stage-policy-drafts',['--path'=>$path])===0){throw new \RuntimeException('Incomplete policy bundle was accepted');}
    if($after->fresh()->body!==$beforeBody){throw new \RuntimeException('Invalid bundle modified content');}
    echo "QA21 guarded policy import passed: five policies, three languages, idempotence, invalid input rejection, no publication.\n";
}finally{if(is_file($path)){unlink($path);}}
