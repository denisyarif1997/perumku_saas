<?php

namespace App\Notifications;

use App\Models\ItemLoan;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi in-app: status pengajuan pinjam barang berubah untuk warga
 * pemohon (disetujui, ditolak, diserahkan, atau sudah kembali).
 */
class LoanStatusChanged extends Notification
{
    public function __construct(
        public ItemLoan $loan,
        public string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'loan_id' => $this->loan->id,
            'title' => $this->loan->item?->name,
            'excerpt' => Str::limit($this->message, 140),
            'item_name' => $this->loan->item?->name,
            'message' => $this->message,
            'status' => $this->loan->status,
            'status_label' => $this->loan->statusLabel(),
        ];
    }
}
