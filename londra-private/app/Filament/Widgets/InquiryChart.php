<?php
declare(strict_types=1);
namespace App\Filament\Widgets;
use App\Services\DashboardMetrics;
use Illuminate\Contracts\View\View;
use Filament\Widgets\ChartWidget;
final class InquiryChart extends ChartWidget
{
    protected ?string $pollingInterval = '30s';
    public function getHeading(): string { return 'Enquiries over the last 14 days'; }
    public function getDescription(): string { return app(DashboardMetrics::class)->last14Days()['total'].' currently retained enquiries received in these London calendar days. Refreshes every 30 seconds.'; }
    public function getChartAssistiveContent(): View { return view('filament.widgets.inquiry-chart-data', ['days'=>app(DashboardMetrics::class)->last14Days()['days']]); }
    protected function getType(): string { return 'line'; }
    protected function getData(): array
    {
        $days = app(DashboardMetrics::class)->last14Days()['days'];
        return ['datasets'=>[['label'=>'Retained enquiries received','data'=>array_column($days,'count'),'tension'=>0.2]],'labels'=>array_column($days,'label')];
    }
    protected function getOptions(): array { return ['scales'=>['y'=>['beginAtZero'=>true,'ticks'=>['precision'=>0]]],'plugins'=>['legend'=>['display'=>false]]]; }
}
