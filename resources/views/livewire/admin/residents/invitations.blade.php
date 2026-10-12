<div class="space-y-4">
    <div class="flex gap-2">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari nama undangan..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 outline-none focus:border-[#0F172A]">
        </div>
        <a href="{{ route('admin.residents.index') }}" wire:navigate class="flex min-h-[48px] items-center rounded-2xl border px-4 font-semibold">Warga</a>
    </div>

    @if (session('status'))
        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
    @endif

    @if ($lastUrl)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="font-bold text-emerald-900">Undangan {{ $lastName }} siap dibagikan</p>
            <p class="mt-1 break-all font-mono text-[13px]">{{ $lastUrl }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <a href="{{ $lastWaUrl }}" target="_blank" rel="noopener" class="flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-[#25D366] font-semibold text-white">
                    <i data-lucide="send" class="h-4 w-4"></i> Share WA
                </a>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $lastUrl }}');this.textContent='Tersalin!'" class="flex min-h-[48px] items-center justify-center rounded-xl border bg-white font-semibold">Salin Link</button>
            </div>
            <p class="mt-2 text-[12px] text-emerald-800">Berlaku 7 hari. Link sekali pakai — token hanya tampil di sini.</p>
        </div>
    @endif

    <form wire:submit="create" class="space-y-3 rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="font-bold">Buat undangan baru</p>
        <x-ui.field label="Nama warga" :error="$errors->first('name')">
            <input wire:model="name" placeholder="Nama sesuai KTP" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
        </x-ui.field>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.field label="No. WA (opsional)" :error="$errors->first('phone')">
                <input wire:model="phone" inputmode="tel" placeholder="08xx" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
            </x-ui.field>
            <x-ui.field label="Email (opsional)" :error="$errors->first('email')">
                <input wire:model="email" type="email" placeholder="nama@email.com" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
            </x-ui.field>
        </div>
        <x-ui.field label="Rumah" :error="$errors->first('house_id')">
            <select wire:model="house_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3">
                <option value="">Pilih rumah</option>
                @foreach ($houses as $house)
                    <option value="{{ $house->id }}">Rumah {{ $house->fullLabel() }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <button class="flex min-h-[48px] w-full items-center justify-center rounded-xl bg-teal-700 font-semibold text-white" wire:loading.attr="disabled">
            <span wire:loading.remove>Buat Link Undangan</span><span wire:loading>Membuat...</span>
        </button>
    </form>

    <div class="space-y-2">
        @forelse ($invitations as $inv)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4" wire:key="inv-{{ $inv->id }}">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $inv->name }}</p>
                        <p class="text-[13px] text-[#64748B]">Rumah {{ $inv->house?->fullLabel() ?? '-' }} • kedaluwarsa {{ $inv->expires_at?->format('d M Y') }}</p>
                    </div>
                    <x-ui.badge :color="$inv->status === 'pending' ? 'amber' : ($inv->status === 'accepted' ? 'green' : 'red')">{{ $inv->status }}</x-ui.badge>
                </div>
                @if ($inv->status === 'pending')
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <span class="flex min-h-[44px] items-center justify-center rounded-xl bg-slate-100 px-2 text-[12px] text-slate-500">Token sekali tampil saat dibuat</span>
                        <button wire:click="revoke({{ $inv->id }})" wire:confirm="Batalkan undangan ini?" class="flex min-h-[44px] items-center justify-center rounded-xl border border-red-200 font-semibold text-red-700">Batalkan</button>
                    </div>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="send" title="Belum ada undangan" subtitle="Buat link undangan pertama di atas." />
        @endforelse
    </div>
    <div>{{ $invitations->links() }}</div>
</div>
