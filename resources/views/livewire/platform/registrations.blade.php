<div class="space-y-4">
    @if (session('status'))
        <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-bold text-[#134E4A]">Pengajuan Perumahan Baru
            @if ($pendingCount > 0)
                <span class="ml-2 rounded-full bg-amber-100 px-2.5 py-0.5 text-[12px] font-semibold text-amber-800">{{ $pendingCount }} menunggu</span>
            @endif
        </h2>
        <div class="ml-auto flex flex-wrap gap-2">
            <select wire:model.live="statusFilter" class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white px-3 text-[14px]">
                <option value="pending">Menunggu</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
                <option value="">Semua</option>
            </select>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama / kode / email..."
                class="min-h-[44px] rounded-xl border border-[#E2E8F0] bg-white px-3 text-[14px]">
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#EEF2F1] bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-[14px]">
                <thead>
                    <tr class="border-b border-[#EEF2F1] bg-[#F8FAFC] text-[12px] uppercase tracking-wide text-[#64748B]">
                        <th class="px-4 py-3">Pemohon</th>
                        <th class="px-4 py-3">Perumahan</th>
                        <th class="px-4 py-3">Metode</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-[#F1F5F9] last:border-0" wire:key="reg-{{ $row->id }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#0F172A]">{{ $row->name }}</p>
                                <p class="text-[13px] text-[#64748B]">{{ $row->email }} • {{ $row->phone }}</p>
                                @if ($row->hasHouseData())
                                    <p class="mt-1 text-[12px] text-[#64748B]">Rumah: Blok {{ $row->block_code }} No. {{ $row->house_number }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#0F172A]">{{ $row->estate_code }} — {{ $row->estate_name }}</p>
                                <p class="text-[13px] text-[#64748B]">{{ $row->estate_address ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$row->method === 'google' ? 'sky' : 'slate'">{{ $row->method === 'google' ? 'Google' : 'Email' }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :color="$row->status === 'approved' ? 'green' : ($row->status === 'rejected' ? 'red' : 'amber')">{{ $row->status }}</x-ui.badge>
                                @if ($row->status === 'rejected' && $row->reject_reason)
                                    <p class="mt-1 max-w-[220px] text-[12px] text-[#64748B]">{{ $row->reject_reason }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($row->status === 'pending')
                                    <button wire:click="approve({{ $row->id }})" wire:confirm="Setujui {{ $row->estate_name }}? Data akan di-inject ke tabel produksi."
                                        class="font-semibold text-emerald-700">Setujui</button>
                                    <button wire:click="startReject({{ $row->id }})" class="ml-3 font-semibold text-amber-700">Tolak</button>
                                @else
                                    <span class="text-[13px] text-[#94A3B8]">diputus {{ $row->decided_at?->format('d M Y') ?? '-' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-[#64748B]">Belum ada pengajuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $rows->links() }}

    @if ($rejectId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:key="reject-modal">
            <div class="w-full max-w-md rounded-2xl bg-white p-5">
                <h3 class="text-[16px] font-bold text-[#134E4A]">Tolak pengajuan?</h3>
                <p class="mt-1 text-[13px] text-[#64748B]">Opsional: tulis alasan penolakan untuk pemohon.</p>
                <textarea wire:model="rejectReason" rows="3" placeholder="Alasan penolakan (opsional)"
                    class="mt-3 w-full rounded-xl border border-[#E2E8F0] px-3 py-2 text-[14px]"></textarea>
                @error('rejectReason') <p class="mt-1 text-[13px] text-red-600">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="cancelReject" class="rounded-xl border px-4 py-2 text-[14px] font-semibold">Batal</button>
                    <button wire:click="reject({{ $rejectId }})" class="rounded-xl bg-amber-600 px-4 py-2 text-[14px] font-semibold text-white">Tolak</button>
                </div>
            </div>
        </div>
    @endif
</div>
