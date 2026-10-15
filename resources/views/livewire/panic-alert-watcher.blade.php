{{-- Satu root element (display:contents agar tak mengganggu layout).
    JS di resources/js/panic-alert.js membaca data-alerts setiap Livewire commit:
    ID yang belum pernah terlihat → sirene + banner + system notification. --}}
<div wire:poll.5s data-panic-watcher data-alerts='@json($alerts)' class="contents"></div>
