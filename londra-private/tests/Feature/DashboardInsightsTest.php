<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Filament\Widgets\{InquiryChart,InquiryStats};
use App\Models\{DataRequest,Inquiry,PrivacyDelivery};
use App\Services\{DashboardMetrics,InquiryErasure};
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\PortalFixtures;
use Tests\TestCase;
final class DashboardInsightsTest extends TestCase
{
    use RefreshDatabase,PortalFixtures;
    protected function setUp(): void
    {
        parent::setUp();$this->seed();$this->withoutVite();
        $this->actingAs($this->syntheticAdministrator());
        Filament::setCurrentPanel('admin');Filament::bootCurrentPanel();
    }
    private function dataRequest(string $status,bool $verified=true,bool $overdue=true): DataRequest
    {
        return DataRequest::create(['reference'=>'LT-DP-'.Str::ulid(),'submission_key'=>bin2hex(random_bytes(32)),'payload_fingerprint'=>bin2hex(random_bytes(32)),'email'=>'guest@example.test','email_lookup'=>hash_hmac('sha256','guest@example.test',config('app.key')),'locale'=>'en','status'=>$status,'verified_at'=>$verified?now()->subHour():null,'due_at'=>$overdue?now()->subMinute():now()->addDay(),'inquiry_cutoff_id'=>0]);
    }
    public function test_dashboard_counts_track_actual_enquiry_privacy_and_delivery_transitions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00 UTC'));
        $records=[
            $this->syntheticInquiry(['follow_up_at'=>now()->subMinute()]),
            $this->syntheticInquiry(['follow_up_at'=>now()->addHour()]),
            $this->syntheticInquiry(['status'=>'contacted','follow_up_at'=>now()->subMinute()]),
            $this->syntheticInquiry(['status'=>'closed','follow_up_at'=>now()->subMinute()]),
            $this->syntheticInquiry(['status'=>'awaiting_response']),
        ];
        $requests=[
            $this->dataRequest('received',false),$this->dataRequest('in_review'),$this->dataRequest('database_erased'),
            $this->dataRequest('in_review',true,false),$this->dataRequest('completed'),$this->dataRequest('refused'),$this->dataRequest('cancelled'),
        ];
        $statuses=['pending','queued','retrying','failed','smtp_accepted'];$deliveries=[];
        foreach($statuses as $index=>$status){
            $deliveries[]=$records[$index]->deliveries()->create(['kind'=>'customer','status'=>$status,'smtp_accepted_at'=>$status==='smtp_accepted'?now():null]);
            PrivacyDelivery::create(['data_request_id'=>$requests[$index]->id,'kind'=>'verify','status'=>$status,'smtp_accepted_at'=>$status==='smtp_accepted'?now():null]);
        }
        $records[0]->deliveries()->create(['kind'=>'admin','status'=>'failed','smtp_accepted_at'=>now()]);
        PrivacyDelivery::create(['data_request_id'=>$requests[0]->id,'kind'=>'admin','status'=>'failed','smtp_accepted_at'=>now()]);
        $metrics=app(DashboardMetrics::class);
        $this->assertSame(['total_enquiries'=>5,'new_enquiries'=>2,'follow_ups_due'=>2,'enquiry_mail_failed'=>1,'enquiry_mail_waiting'=>3,'privacy_mail_failed'=>1,'privacy_mail_waiting'=>3,'mail_failed'=>2,'mail_waiting'=>6,'privacy_in_review'=>2,'privacy_overdue'=>2,'privacy_external_cleanup'=>1,'published_pages'=>33,'active_vehicles'=>4,'media_assets'=>13],$metrics->summary());
        $records[0]->update(['status'=>'contacted','follow_up_at'=>now()->addHour()]);
        $deliveries[2]->update(['status'=>'smtp_accepted','smtp_accepted_at'=>now()]);
        $requests[1]->update(['status'=>'completed','completed_at'=>now()]);
        $requests[2]->update(['status'=>'completed','completed_at'=>now()]);
        app(InquiryErasure::class)->erase($records[4],'synthetic_test',auth()->id());
        $after=$metrics->summary();
        $this->assertSame(4,$after['total_enquiries']);$this->assertSame(1,$after['new_enquiries']);$this->assertSame(1,$after['follow_ups_due']);
        $this->assertSame(5,$after['mail_waiting']);$this->assertSame(2,$after['mail_failed']);
        $this->assertSame(1,$after['privacy_in_review']);$this->assertSame(0,$after['privacy_overdue']);$this->assertSame(0,$after['privacy_external_cleanup']);
    }
    public function test_chart_uses_london_calendar_boundaries_across_both_daylight_saving_changes(): void
    {
        $cases=[
            ['now'=>'2026-03-29 12:00:00 UTC','first'=>'2026-03-16','last'=>'2026-03-29','start'=>'2026-03-16 00:00:00 UTC','end'=>'2026-03-29 23:00:00 UTC','same_day'=>['2026-03-29 00:30:00 UTC','2026-03-29 01:30:00 UTC']],
            ['now'=>'2026-10-25 12:00:00 UTC','first'=>'2026-10-12','last'=>'2026-10-25','start'=>'2026-10-11 23:00:00 UTC','end'=>'2026-10-26 00:00:00 UTC','same_day'=>['2026-10-25 00:30:00 UTC','2026-10-25 01:30:00 UTC']],
        ];
        foreach($cases as $case){
            Inquiry::query()->delete();$this->travelTo(CarbonImmutable::parse($case['now']));
            $start=CarbonImmutable::parse($case['start']);$end=CarbonImmutable::parse($case['end']);
            $timestamps=[$start->subSecond(),$start,$start->addSecond(),CarbonImmutable::parse($case['same_day'][0]),CarbonImmutable::parse($case['same_day'][1]),$end->subSecond(),$end];
            foreach($timestamps as $timestamp){$record=$this->syntheticInquiry();DB::table('inquiries')->where('id',$record->id)->update(['created_at'=>$timestamp->utc()->format('Y-m-d H:i:s')]);}
            $chart=app(DashboardMetrics::class)->last14Days();
            $this->assertCount(14,$chart['days']);$this->assertSame($case['first'],$chart['days'][0]['date']);$this->assertSame($case['last'],$chart['days'][13]['date']);
            $this->assertSame(2,$chart['days'][0]['count']);$this->assertSame(3,$chart['days'][13]['count']);$this->assertSame(5,$chart['total']);
            $this->assertSame(0,array_sum(array_column(array_slice($chart['days'],1,12),'count')));
        }
    }
    private function widgetValue(string $html,string $label): int
    {
        $xpath=$this->xpath($html);
        $query='//span[contains(concat(" ",normalize-space(@class)," ")," fi-wi-stats-overview-stat-label ") and normalize-space(.)="'.$label.'"]/ancestor::div[contains(concat(" ",normalize-space(@class)," ")," fi-wi-stats-overview-stat ")][1]//div[contains(concat(" ",normalize-space(@class)," ")," fi-wi-stats-overview-stat-value ")]';
        $values=$xpath->query($query);$this->assertSame(1,$values->length,$label);
        return (int)trim($values->item(0)->textContent);
    }
    public function test_visible_widgets_poll_and_render_new_database_values_with_an_accessible_chart_summary(): void
    {
        $widget=Livewire::withoutLazyLoading()->test(InquiryStats::class)->assertSee('wire:poll.30s',false);
        $this->assertSame(0,$this->widgetValue($widget->html(),'New enquiries'));
        $record=$this->syntheticInquiry();
        $widget->call('$refresh');
        $this->assertSame(1,$this->widgetValue($widget->html(),'New enquiries'));
        $record->update(['status'=>'closed']);$widget->call('$refresh');
        $this->assertSame(0,$this->widgetValue($widget->html(),'New enquiries'));
        $this->assertSame(1,$this->widgetValue($widget->html(),'Retained enquiries'));
        $chart=Livewire::withoutLazyLoading()->test(InquiryChart::class)->assertSee('wire:poll.30s',false)->assertSee('London');
        $xpath=$this->xpath($chart->html());
        $this->assertGreaterThanOrEqual(1,$xpath->query('//table')->length);
        $this->assertSame(14,$xpath->query('//table//tbody/tr')->length);
    }
}
