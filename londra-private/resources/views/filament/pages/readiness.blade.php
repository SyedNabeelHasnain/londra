<x-filament-panels::page><div wire:poll.30s class="space-y-6">
<x-filament::section heading="Release checks"><p>These checks describe configuration and content state. They are not a security certification, legal approval or proof of delivery to an email inbox.</p></x-filament::section>
<x-filament::section heading="Hostinger Cron Jobs"><p>Select the hosting account’s PHP 8.5 command and append these arguments. Run it every minute. The last successful run appears below.</p><p><code>{{ $this->cronArguments() }}</code></p></x-filament::section>
@foreach ($this->checks() as $check)<x-filament::section :heading="$check['label']"><p>{{ $check['ok'] ? 'Pass' : 'Pending' }}: {{ $check['detail'] }}</p></x-filament::section>@endforeach
</div></x-filament-panels::page>
