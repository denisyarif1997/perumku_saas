<div class="space-y-4">
    @if (session('success'))
        <x-ui.alert type="success" icon="check-circle-2">{{ session('success') }}</x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert type="danger" icon="alert-circle">{{ session('error') }}</x-ui.alert>
    @endif

    {{-- Ringkasan --}}
    <div class="grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Total Kas Masuk</p>
            <p class="mt-1 text-[18px] font-bold text-emerald-600">@rupiah($totalIn)</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Total Kas Keluar</p>
            <p class="mt-1 text-[18px] font-bold text-red-600">@rupiah($totalOut)</p>
        </div>
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <p class="text-[13px] text-[#64748B]">Total Transfer Antar Kas</p>
            <p class="mt-1 text-[18px] font-bold">@rupiah($totalTransfer)</p>
        </div>
    </div>

    {{-- Form transaksi --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
        <p class="mb-3 font-bold">Catat Transaksi</p>

        <div class="mb-4 grid grid-cols-3 gap-2">
            <button wire:click="setType('in')" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border text-[14px] font-semibold {{ $type === 'in' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-[#E2E8F0]' }}">
                <i data-lucide="arrow-down-left" class="h-4 w-4"></i> Kas Masuk
            </button>
            <button wire:click="setType('out')" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border text-[14px] font-semibold {{ $type === 'out' ? 'border-red-600 bg-red-50 text-red-700' : 'border-[#E2E8F0]' }}">
                <i data-lucide="arrow-up-right" class="h-4 w-4"></i> Kas Keluar
            </button>
            <button wire:click="setType('transfer')" class="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border text-[14px] font-semibold {{ $type === 'transfer' ? 'border-[#0F172A] bg-slate-100' : 'border-[#E2E8F0]' }}">
                <i data-lucide="arrow-left-right" class="h-4 w-4"></i> Transfer
            </button>
        </div>

        <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
            <x-ui.field label="{{ $type === 'transfer' ? 'Dari Kas' : 'Kas' }}" :error="$errors->first('cash_account_id')">
                <select wire:model="cash_account_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                    <option value="">Pilih kas</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }} (Saldo @rupiah($account->currentBalance()))</option>
                    @endforeach
                </select>
            </x-ui.field>

            @if ($type === 'transfer')
                <x-ui.field label="Ke Kas" :error="$errors->first('destination_account_id')">
                    <select wire:model="destination_account_id" class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
                        <option value="">Pilih kas tujuan</option>
                        @foreach ($accounts as $account)
                            @if ((string) $account->id !== $cash_account_id)
                                <option value="{{ $account->id }}">{{ $account->name }} (Saldo @rupiah($account->currentBalance()))</option>
                            @endif
                        @endforeach
                    </select>
                </x-ui.field>
            @endif

            <x-ui.field label="Nominal (Rp)" :error="$errors->first('amount')">
                <input wire:model="amount" type="number" min="1" step="0.01" inputmode="numeric" placeholder="50000"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Tanggal" :error="$errors->first('transaction_date')">
                <input wire:model="transaction_date" type="date"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Kategori" :error="$errors->first('category')">
                <input wire:model="category" placeholder="Contoh: {{ $type === 'in' ? 'iuran, donasi' : 'operasional, kebersihan' }}"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
            </x-ui.field>

            <x-ui.field label="Referensi" :error="$errors->first('reference')">
                <input wire:model="reference" placeholder="No. bukti / nota (opsional)"
                    class="min-h-[48px] w-full rounded-xl border border-[#E2E8F0] px-3 text-[15px] outline-none focus:border-[#0F172A]">
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

    {{-- Filter --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"></i>
            <input wire:model.live.debounce.300ms="search" placeholder="Cari keterangan / kategori / referensi..."
                class="min-h-[48px] w-full rounded-2xl border border-[#E2E8F0] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0F172A]">
        </div>
        <select wire:model.live="filterType" class="min-h-[48px] rounded-2xl border border-[#E2E8F0] bg-white px-3 text-[15px]">
            <option value="">Semua Jenis</option>
            <option value="in">Kas Masuk</option>
            <option value="out">Kas Keluar</option>
            <option value="transfer">Transfer</option>
            <option value="reversal">Pembalikan Kas</option>
        </select>
    </div>

    {{-- Daftar transaksi (mobile) --}}
    <div class="grid gap-3 md:hidden">
        @forelse ($transactions as $transaction)
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ $transaction->typeLabel() }} — {{ $transaction->account?->name }}</p>
                        @if ($transaction->type === 'transfer' && $transaction->destinationAccount)
                            <p class="text-[13px] text-[#64748B]">Ke {{ $transaction->destinationAccount->name }}</p>
                        @endif
                        <p class="text-[13px] text-[#64748B]">{{ $transaction->transaction_date?->format('d/m/Y') }}{{ $transaction->category ? ' · '.$transaction->category : '' }}</p>
                    </div>
                    <p class="whitespace-nowrap font-bold {{ $transaction->type === 'in' ? 'text-emerald-600' : ($transaction->type === 'out' ? 'text-red-600' : ($transaction->type === 'reversal' ? 'text-amber-600' : '')) }}">@rupiah($transaction->amount)</p>
                </div>
                @if ($transaction->description)
                    <p class="mt-1 text-[13px] text-[#64748B]">{{ $transaction->description }}</p>
                @endif
                <div class="mt-3 flex items-center justify-between">
                    <p class="text-[13px] text-[#64748B]">{{ $transaction->creator?->name }}</p>
                    <button wire:click="delete({{ $transaction->id }})" wire:confirm="Hapus transaksi ini?" class="font-semibold text-red-600">Hapus</button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="wallet" title="Belum ada transaksi" subtitle="Catat kas masuk, kas keluar, atau transfer antar kas." />
        @endforelse
    </div>


    {{-- Tabel transaksi (desktop) --}}
    <div class="hidden overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white md:block">
        <table class="w-full text-left text-[14px]">
            <thead class="bg-slate-50 text-[13px] text-[#64748B]">
                <tr>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Jenis</th>
                    <th class="px-4 py-3">Kas</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Keterangan</th>
                    <th class="px-4 py-3 text-right">Nominal</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr class="border-t border-[#E2E8F0]">
                        <td class="px-4 py-3 text-[#64748B]">{{ $transaction->transaction_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge color="{{ $transaction->type === 'in' ? 'green' : ($transaction->type === 'out' ? 'red' : ($transaction->type === 'reversal' ? 'amber' : 'sky')) }}">{{ $transaction->typeLabel() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 font-semibold">
                            {{ $transaction->account?->name }}
                            @if ($transaction->type === 'transfer' && $transaction->destinationAccount)
                                <span class="font-normal text-[#64748B]">→ {{ $transaction->destinationAccount->name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $transaction->category ?? '-' }}</td>
                        <td class="px-4 py-3 text-[#64748B]">{{ $transaction->reference ? '['.$transaction->reference.'] ' : '' }}{{ $transaction->description ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $transaction->type === 'in' ? 'text-emerald-600' : ($transaction->type === 'out' ? 'text-red-600' : ($transaction->type === 'reversal' ? 'text-amber-600' : '')) }}">@rupiah($transaction->amount)</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="delete({{ $transaction->id }})" wire:confirm="Hapus transaksi ini?" class="font-semibold text-red-600">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-[#64748B]">Belum ada transaksi kas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>

