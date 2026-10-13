<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\{Inquiry,NotificationDelivery,PrivacyDelivery,DataRequest,Page,Vehicle,MediaAsset};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
final class DashboardMetrics
{
    public function summary(): array
    {
        $privacy = Schema::hasTable('data_requests');
        $privacyMail = Schema::hasTable('privacy_deliveries');
        $enquiryFailed = NotificationDelivery::where('status','failed')->whereNull('smtp_accepted_at')->count();
        $enquiryWaiting = NotificationDelivery::whereIn('status',['pending','queued','retrying'])->whereNull('smtp_accepted_at')->count();
        $privacyFailed = $privacyMail ? PrivacyDelivery::where('status','failed')->whereNull('smtp_accepted_at')->count() : 0;
        $privacyWaiting = $privacyMail ? PrivacyDelivery::whereIn('status',['pending','queued','retrying'])->whereNull('smtp_accepted_at')->count() : 0;
        return [
            'total_enquiries' => Inquiry::count(),
            'new_enquiries' => Inquiry::where('status','new')->count(),
            'follow_ups_due' => Inquiry::where('status','!=','closed')->whereNotNull('follow_up_at')->where('follow_up_at','<=',now())->count(),
            'enquiry_mail_failed' => $enquiryFailed,
            'enquiry_mail_waiting' => $enquiryWaiting,
            'privacy_mail_failed' => $privacyFailed,
            'privacy_mail_waiting' => $privacyWaiting,
            'mail_failed' => $enquiryFailed + $privacyFailed,
            'mail_waiting' => $enquiryWaiting + $privacyWaiting,
            'privacy_in_review' => $privacy ? DataRequest::where('status','in_review')->whereNotNull('verified_at')->count() : 0,
            'privacy_overdue' => $privacy ? DataRequest::whereIn('status',['in_review','database_erased'])->whereNotNull('verified_at')->whereNotNull('due_at')->where('due_at','<=',now())->count() : 0,
            'privacy_external_cleanup' => $privacy ? DataRequest::where('status','database_erased')->count() : 0,
            'published_pages' => Page::where('published',true)->count(),
            'active_vehicles' => Vehicle::where('active',true)->count(),
            'media_assets' => MediaAsset::count(),
        ];
    }
    public function last14Days(): array
    {
        $start = CarbonImmutable::now('Europe/London')->startOfDay()->subDays(13);
        $end = $start->addDays(14);
        $days = [];
        for ($index = 0; $index < 14; $index++) {
            $day = $start->addDays($index);
            $days[$day->format('Y-m-d')] = ['date'=>$day->format('Y-m-d'),'label'=>$day->format('d M'),'count'=>0];
        }
        foreach (Inquiry::where('created_at','>=',$start->utc())->where('created_at','<',$end->utc())->pluck('created_at') as $timestamp) {
            $date = CarbonImmutable::parse($timestamp,'UTC')->setTimezone('Europe/London')->format('Y-m-d');
            $days[$date]['count']++;
        }
        return ['days'=>array_values($days),'total'=>array_sum(array_column($days,'count'))];
    }
}
