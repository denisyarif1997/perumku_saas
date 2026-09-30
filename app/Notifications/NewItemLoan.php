<?php

namespace App\Notifications;

use App\Models\ItemLoan;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app: ada pengajuan pinjam barang baru dari warga
 * (untuk pengelola inventaris).
 */
class NewItemLoan extends Notification
{
    public function __construct(
        public ItemLoan $loan,
        public string $actor,
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
            'item_name' => $this->loan->item?->name,
            'borrower' => $this->loan->borrowerName(),
            'purpose' => $this->loan->purpose,
            'actor' => $this->actor,
        ];
    }
}
