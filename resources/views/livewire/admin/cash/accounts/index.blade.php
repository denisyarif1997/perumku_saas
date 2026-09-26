<div class="space-y-4">
    <div class="relative">
        <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
        <input wire:model.live.debounce.300ms="search" placeholder="Cari nama kas / no. rekening..."
            class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
    </div>

    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Form kas --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <p class="font-bold">{{ $editingId ? 'Ubah Kas' : 'Tambah Kas' }}</p>
            @if ($editingId)
                <button wire:click="cancel" class="text-[14px] font-semibold text-[#64748B]">Batal</button>
            @endif
        </div>

        <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
            <x-ui.estate-field
                :estates="$estates"
                :error="$errors->first('housing_estate_id')"
            />

            <x-ui.field label="Nama Kas" :error="$errors->first('name')">
                <input wire:model="name" placeholder="Contoh: Kas RT 01 / BCA 123456789"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Jenis Kas" :error="$errors->first('type')">
                <select wire:model="type" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="cash">Kas Tunai</option>
                    <option value="bank">Rekening Bank</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Saldo Awal (Rp)" :error="$errors->first('opening_balance')">
                <input wire:model="opening_balance" type="number" step="0.01" inputmode="numeric"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="No. Rekening (bank, opsional)" :error="$errors->first('account_number')">
                <input wire:model="account_number" placeholder="1234567890"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Atas Nama (opsional)" :error="$errors->first('account_holder')">
                <input wire:model="account_holder" placeholder="Nama pemilik rekening"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Status" :error="$errors->first('status')">
                <select wire:model="status" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="active">Aktif</option>
                    <option value="inactive">Tidak Aktif</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Keterangan" :error="$errors->first('description')">
                <input wire:model="description" placeholder="Opsional"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <div class="md:col-span-2">
                <button type="submit" class="min-h-[48px] w-full rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 shadow-lg shadow-teal-700/30 px-4 font-semibold text-white md:w-auto md:px-8">Simpan</button>
            </div>
        </form>
    </div>

    {{-- Daftar kas (mobile) --}}
    <div class="grid gap-3 md:hidden">
        @forelse ($accounts as $account)
            @php $balance = $account->currentBalance(); @endphp
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-bold">{{ $account->name }}</p>
                        <p class="text-[13px] text-[#64748B]">{{ $account->estate?->name ?? 'Semua Perumahan' }} · {{ $account->type === 'bank' ? 'Bank' : 'Tunai' }}</p>
                    </div>
                    <x-ui.badge color="{{ $account->status === 'active' ? 'green' : 'slate' }}">{{ $account->status }}</x-ui.badge>
                </div>
                <p class="mt-2 text-[18px] font-bold @if ($balance < 0) text-red-600 @endif">@rupiah($balance)</p>
                @if ($account->account_number)
                    <p class="text-[13px] text-[#64748B]">Rek: {{ $account->account_number }} a.n. {{ $account->account_holder }}</p>
                @endif
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button wire:click="edit({{ $account->id }})" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] text-[14px] font-semibold"><i data-lucide="pencil" class="h-4 w-4"></i> Ubah</button>
                    <button wire:click="delete({{ $account->id }})" wire:confirm="Hapus kas {{ $account->name }}?" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-red-200 text-[14px] font-semibold text-red-700"><i data-lucide="trash-2" class="h-4 w-4"></i> Hapus</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="wallet" title="Belum ada kas" subtitle="Tambahkan kas dahulu sebelum mencatat transaksi." />
        @endforelse
    </div>


    {{-- Tabel kas (desktop) --}}
    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Nama Kas</th>
                    <th class="px-4 py-3">Perumahan</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">Rekening</th>
                    <th class="px-4 py-3 text-right">Saldo Awal</th>
                    <th class="px-4 py-3 text-right">Saldo Sekarang</th>
                    <th class="px-4 py-3">Transaksi</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    @php $balance = $account->currentBalance(); @endphp
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 font-semibold">{{ $account->name }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $account->estate?->name ?? 'Semua Perumahan' }}</td>
                        <td class="px-4 py-3">{{ $account->type === 'bank' ? 'Bank' : 'Tunai' }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $account->account_number ? $account->account_number.' a.n. '.$account->account_holder : '-' }}</td>
                        <td class="px-4 py-3 text-right">@rupiah($account->opening_balance)</td>
                        <td class="px-4 py-3 text-right font-semibold @if ($balance < 0) text-red-600 @endif">@rupiah($balance)</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $account->transactions_count + $account->incoming_transfers_count }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="{{ $account->status === 'active' ? 'green' : 'slate' }}">{{ $account->status }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="edit({{ $account->id }})" class="font-semibold">Ubah</button>
                            <button wire:click="delete({{ $account->id }})" wire:confirm="Hapus kas ini?" class="ml-3 font-semibold text-red-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-[#64748B]">Belum ada kas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $accounts->links() }}</div>
</div>

