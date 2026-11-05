<?php
declare(strict_types=1);
require __DIR__.'/../app/Domain/LocalePolicy.php';
require __DIR__.'/../app/Domain/ReleasePolicy.php';
require __DIR__.'/../app/Domain/CsvSafety.php';
require __DIR__.'/../app/Domain/InquiryStatus.php';
require __DIR__.'/../app/Domain/PolicyFingerprint.php';
require __DIR__.'/../app/Services/ReleaseUpdater.php';
require __DIR__.'/../app/Presentation/Html.php';
require __DIR__.'/../app/Presentation/SiteViewModel.php';
use App\Domain\{LocalePolicy,ReleasePolicy,CsvSafety,InquiryStatus,PolicyFingerprint};
use App\Presentation\{Html,SiteViewModel};
$checks=[];
$check=static function(string $name,bool $passed) use(&$checks):void{$checks[]=['check'=>$name,'passed'=>$passed];};
$read=static fn($path)=>json_decode(file_get_contents(__DIR__.'/../'.$path),true,512,JSON_THROW_ON_ERROR);
foreach([
 ['it','en','es','it'],['es','it','it-IT,it;q=0.9','es'],['en','it','it','en'],[null,'es','it','es'],[null,null,'it-IT,it;q=0.9,en;q=0.8','it'],[null,null,'es-ES','es'],[null,null,'en-GB,en;q=0.9','en'],[null,null,null,'en'],['fr','xx','it','it'],['xx','it','es','it'],[null,null,'fr-FR,de;q=0.8','en'],
 [null,null,'fr;q=1, es;q=0.4, it;q=0.7','it'],[null,null,'IT','it'],[null,null,'es-419','es'],[null,null,'it;q=0','en'],[null,null,'en;q=0.5, es;q=0.5','en'],[null,null,'*','en'],[null,null,'it;q=abc','it'],[null,null,str_repeat('a,',600),'en'],[null,null,'<script>','en']
]as$i=>$c){$check('Locale priority '.$i,LocalePolicy::choose($c[0],$c[1],$c[2])===$c[3]);}
foreach(['','services','private-tours/london','flota/clase-e'] as$p){$check('Valid local path '.$p,LocalePolicy::safePath($p));}
foreach(['//evil.example','https://evil.example','../admin','services/../admin','services?url=x','/admin','A','%2Fadmin','admin/','a\\b','a//b','a--b']as$p){$check('Reject path '.$p,!LocalePolicy::safePath($p));}
foreach(range(0,15)as$mask){$f=['accept_inquiries'=>(bool)($mask&1),'privacy_approved'=>(bool)($mask&2),'publication_approved'=>(bool)($mask&4),'preview'=>(bool)($mask&8)];$check('Enquiry publication truth table with retired flag '.$mask,ReleasePolicy::inquiryAllowed($f)===(($mask&7)===7));}
foreach(range(0,7)as$mask){$f=['indexing_enabled'=>(bool)($mask&1),'publication_approved'=>(bool)($mask&2),'preview'=>(bool)($mask&4)];$check('Indexing truth table with retired flag '.$mask,ReleasePolicy::indexAllowed($f)===(($mask&3)===3));}
$check('Publish needs all languages',!ReleasePolicy::canPublish(false,false,['en','it']));
$check('Policy needs approval',!ReleasePolicy::canPublish(true,false,['en','it','es']));
$check('All languages and approval accepted',ReleasePolicy::canPublish(true,true,['es','it','en']));
$check('Locale duplicates cannot bypass count',!ReleasePolicy::canPublish(false,false,['en','en','it']));
foreach(['=HYPERLINK("x")','+cmd','-10','@SUM(1)',"\t=1",'  =1']as$value){$check('CSV neutralises '.bin2hex($value),str_starts_with(CsvSafety::cell($value),"'"));}
$check('Ordinary CSV preserved',CsvSafety::cell('Enquiry received')==='Enquiry received');
$check('HTML escaping',Html::e('<script>" &')==='&lt;script&gt;&quot; &amp;');
$check('CRM statuses fixed',array_keys(InquiryStatus::options())===['new','contacted','awaiting_response','closed']);
$pages=$read('database/content/pages.json');$vehicles=$read('database/content/vehicles.json');$media=array_column($read('database/content/media.json'),null,'key');
$alts=$read('database/content/media-alt.json');foreach($media as$key=>&$asset){$asset['alt']=$alts[$key];}unset($asset);
$check('Four confirmed passenger capacities',array_column($vehicles,'passengers','code')===['e-class'=>3,'s-class'=>3,'v-class'=>7,'vito'=>8]);
$check('No invented luggage',count(array_filter($vehicles,fn($v)=>$v['large_luggage']!==null||$v['cabin_luggage']!==null||$v['luggage_approved']))===0);
$uiKeys=array_keys($read('lang/en/ui.json'));$publicCount=0;
foreach(['en','it','es']as$locale){
 $ui=$read('lang/'.$locale.'/ui.json');$check('Interface keys match '.$locale,array_keys($ui)===$uiKeys);$seen=[];
 foreach($pages as$page){$t=$page['translations'][$locale];$check('Unique '.$locale.' slug '.$page['key'],!isset($seen[$t['slug']]));$seen[$t['slug']]=true;$check('Safe '.$locale.' slug '.$page['key'],LocalePolicy::safePath($t['slug']));
  if($page['approval_required']){$check('Policy withheld '.$locale.' '.$page['key'],!$t['ready']&&empty($page['published']));continue;}
  $publicCount++;$v=SiteViewModel::build($pages,$vehicles,$media,$ui,$locale,$page['key'],true);
  $check('Three equivalents '.$locale.' '.$page['key'],count($v['alternates'])===3&&count($v['language_choices'])===3);
  $check('Self canonical '.$locale.' '.$page['key'],$v['canonical']===$v['alternates'][$locale]);
  $check('Unconfigured presentation does not collect or index '.$locale.' '.$page['key'],!$v['accept_inquiries']&&!$v['indexable']);
  $check('Image exists '.$locale.' '.$page['key'],isset($media[$page['image_key']]));
  $check('Policy absent from navigation '.$locale.' '.$page['key'],!isset($v['links']['privacy']));
  $schema=json_decode($v['schema'],true,512,JSON_THROW_ON_ERROR);$check('Valid structured data '.$locale.' '.$page['key'],$schema['@context']==='https://schema.org');
 }
}
$check('99 real translated public pages',$publicCount===99);
$policyPages=array_column(array_filter($pages,fn($page)=>$page['kind']==='policy'),null,'key');$approval=$read('database/content/policy-approval.json');
$check('Policy approval names only policy pages',array_diff(array_keys($approval['pages']),array_keys($policyPages))===[]&&is_string($approval['id'])&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$approval['approved_on'])===1);
foreach($approval['pages'] as$key=>$fingerprints){foreach(['en','it','es']as$locale){$check('Approved policy wording unchanged '.$locale.' '.$key,isset($fingerprints[$locale],$policyPages[$key])&&hash_equals($fingerprints[$locale],PolicyFingerprint::of($policyPages[$key]['translations'][$locale])));}}
foreach($read('database/content/policies.json')['records'] as$record){foreach($record['translations'] as$locale=>$translation){foreach(['slug','title','intro','body','seo_title','meta_description']as$field){$check('Policy import matches page content '.$locale.' '.$record['key'].' '.$field,($translation[$field]??null)===($policyPages[$record['key']]['translations'][$locale][$field]??false));}}}
$v=SiteViewModel::build($pages,$vehicles,$media,$read('lang/en/ui.json'),'en','home',true);
$v['page']['seo_title']='</script><script>alert(1)</script>';$json=SiteViewModel::schema($v);
$check('Structured data script closure escaped',!str_contains($json,'</script>'));
$zone=new DateTimeZone('Europe/London');
foreach([['2026-03-29T00:30:00Z','00:30 GMT'],['2026-03-29T01:30:00Z','02:30 BST'],['2026-10-25T00:30:00Z','01:30 BST'],['2026-10-25T01:30:00Z','01:30 GMT']]as[$input,$expected]){$check('London DST '.$input,(new DateTimeImmutable($input))->setTimezone($zone)->format('H:i T')===$expected);}
$failures=array_values(array_filter($checks,fn($row)=>!$row['passed']));
$result=['runtime'=>PHP_VERSION,'method'=>'Pure PHP domain, content and presentation checks. No Laravel, Filament, MySQL, SMTP or Hostinger execution.','checks'=>count($checks),'passed'=>count($checks)-count($failures),'failures'=>$failures,'results'=>$checks];
if(!is_dir(__DIR__.'/../reports')){mkdir(__DIR__.'/../reports',0775,true);}
file_put_contents(__DIR__.'/../reports/domain-tests.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
echo json_encode(['checks'=>$result['checks'],'passed'=>$result['passed'],'failures'=>$failures],JSON_PRETTY_PRINT).PHP_EOL;
exit($failures?1:0);
