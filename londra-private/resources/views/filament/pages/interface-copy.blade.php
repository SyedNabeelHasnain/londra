<x-filament-panels::page>
    <p>Language changes retain the current edits on this screen. Save each language before leaving the page. Page headings and body content are managed under Pages.</p>
    <form wire:submit="save">{{ $this->form }}<x-filament::button type="submit" class="mt-6">Save translations</x-filament::button></form>
</x-filament-panels::page>
