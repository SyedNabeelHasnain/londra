<x-filament-panels::page>
<form wire:submit="save">{{ $this->form }}<x-filament::button type="submit" class="mt-6">Save contact settings</x-filament::button></form>
<x-filament::section heading="Protected configuration"><p>The six brand colours and English-only administration are fixed by the approved brief. SMTP secrets and publication gates are configured outside the public directory. No third-party tracking is enabled by this screen.</p></x-filament::section>
</x-filament-panels::page>
