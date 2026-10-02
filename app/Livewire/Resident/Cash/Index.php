<?php

namespace App\Livewire\Resident\Cash;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tampilan kas untuk warga: saldo kas dan riwayat mutasinya.
 *
 * Halaman ini sengaja read-only. Warga boleh tahu kas ada dan sudah
 * bergerak ke mana, tapi tidak boleh melihat siapa yang membayar — makanya
 * kolom keterangan, nomor invoice, dan petugas tidak ikut dimuat (lihat
 * transactionQuery()).
 */
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->resident_id, 403, 'Akun ini tidak terhubung dengan data warga.');
    }

    /**
     * Riwayat mutasi kas, dipangkas di level kolom.
     *
     * Kolom yang dimuat sengaja sedikit: description memuat nama warga
     * ("Pembayaran Iuran - 2026... — Budi"), reference adalah nomor invoice yang
     * bisa ditelusuri balik ke pembayar, dan created_by menunjuk petugas.
     * Ketiganya tidak boleh masuk ke payload halaman ini. Memangkas di
     * select() membuat kebocoran mustahil terjadi walau view berubah.
     */
    protected function transactionQuery(): Builder
    {
        return CashTransaction::query()
            ->select(['id', 'cash_account_id', 'destination_account_id', 'type', 'amount', 'transaction_date', 'category'])
            ->with(['account:id,name', 'destinationAccount:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }

    #[Layout('layouts.resident', ['title' => 'Kas Warga'])]
    public function render()
    {
        $accounts = CashAccount::query()->orderBy('name')->get();

        return view('livewire.resident.cash.index', [
            'accounts' => $accounts,
            // currentBalance() menjumlahkan mutasi per akun; jumlah akun kas
            // sedikit, jadi pola ini sama dengan yang dipakai halaman admin.
            'totalBalance' => $accounts->sum(fn (CashAccount $account) => $account->currentBalance()),
            'inThisMonth' => (float) $this->transactionQuery()
                ->where('type', CashTransaction::TYPE_IN)
                ->whereBetween('transaction_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('amount'),
            'outThisMonth' => (float) $this->transactionQuery()
                ->where('type', CashTransaction::TYPE_OUT)
                ->whereBetween('transaction_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('amount'),
            'transactions' => $this->transactionQuery()->paginate(15),
        ]);
    }
}
