<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$code=\Illuminate\Support\Facades\Artisan::call('londra:scheduler-heartbeat');
if($code!==0){throw new \RuntimeException('Heartbeat command failed');}
$setting=\App\Models\Setting::find('scheduler-heartbeat');
if(!$setting||!is_array($setting->value)||!isset($setting->value['last_ran_at'])){throw new \RuntimeException('Missing heartbeat setting');}
$checks=app(\App\Services\ReadinessReport::class)->checks();
$found=false;
foreach($checks as $check){if($check['label']==='Cron scheduler heartbeat'){$found=true;if(!$check['ok']){throw new \RuntimeException('Current heartbeat not detected');}}}
if(!$found){throw new \RuntimeException('Heartbeat not reported in CMS readiness');}
$setting->update(['value'=>['last_ran_at'=>now()->subHours(2)->toIso8601String()]]);
foreach(app(\App\Services\ReadinessReport::class)->checks() as $check){if($check['label']==='Cron scheduler heartbeat'&&$check['ok']){throw new \RuntimeException('Stale cron execution falsely marked healthy');}}
\Illuminate\Support\Facades\Artisan::call('schedule:list');
if(!str_contains(\Illuminate\Support\Facades\Artisan::output(),'londra:scheduler-heartbeat')){throw new \RuntimeException('Scheduled heartbeat missing from task list');}
echo "QA22 scheduler heartbeat passed: current, stale and registered schedule checks.\n";
