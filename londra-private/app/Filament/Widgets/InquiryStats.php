<?php
declare(strict_types=1);
namespace App\Filament\Widgets;
use App\Services\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
final class InquiryStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';
    protected ?string $description = 'Current retained records. Counts refresh every 30 seconds while this dashboard is visible.';
    protected function getStats(): array
    {
        $counts = app(DashboardMetrics::class)->summary();
        return [
            Stat::make('New enquiries',$counts['new_enquiries'])->description('Awaiting first contact')->color('primary'),
            Stat::make('Follow-ups due',$counts['follow_ups_due'])->description('Open enquiries due now')->color('primary'),
            Stat::make('Retained enquiries',$counts['total_enquiries'])->description('Includes new, open and closed records')->color('primary'),
            Stat::make('Failed emails',$counts['mail_failed'])->description('Enquiry '.$counts['enquiry_mail_failed'].' · Privacy '.$counts['privacy_mail_failed'])->color('primary'),
            Stat::make('Emails awaiting processing',$counts['mail_waiting'])->description('SMTP acceptance is separate from inbox arrival')->color('primary'),
            Stat::make('Privacy requests overdue',$counts['privacy_overdue'])->description('Verified requests with an outstanding decision or cleanup')->color('primary'),
            Stat::make('Privacy requests in review',$counts['privacy_in_review'])->description('Verified identity; decision outstanding')->color('primary'),
            Stat::make('External privacy cleanup',$counts['privacy_external_cleanup'])->description('Database erased; mailbox and backup review outstanding')->color('primary'),
            Stat::make('Published pages',$counts['published_pages'])->description($counts['active_vehicles'].' active vehicles · '.$counts['media_assets'].' media assets')->color('primary'),
        ];
    }
}
