<?php

namespace Database\Factories;

use App\Models\HousingEstate;
use App\Models\InventoryItem;
use App\Models\ItemLoan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemLoan>
 */
class ItemLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Pinjaman hasil factory otomatis terikat ke HousingEstate pertama yang ada,
     * meniru kondisi produksi. Warga diambil dari akun yang sudah ada (dari
     * HousingSeeder) supaya relasi warga–rumah konsisten dengan data lain.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requester = User::query()
            ->whereNotNull('resident_id')
            ->first()
            ?? User::factory()->create();

        return [
            'housing_estate_id' => HousingEstate::query()->value('id'),
            'inventory_item_id' => InventoryItem::factory(),
            'resident_id' => $requester->resident_id,
            'user_id' => $requester->id,
            'purpose' => fake()->randomElement([
                'Untuk acara arisan warga',
                'Dipinjam untuk kegiatan posyandu',
                'Kegiatan 17 Agustus',
                'Takziah tetangga',
            ]),
            'status' => 'requested',
        ];
    }

    /**
     * Pengajuan yang sudah disetujui tapi belum diserahkan.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Barang sedang berada di tangan peminjam.
     */
    public function loaned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'loaned',
            'approved_at' => now()->subDay(),
            'loaned_at' => now(),
        ]);
    }

    /**
     * Barang sudah kembali dan transaksi selesai.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'returned',
            'approved_at' => now()->subDays(2),
            'loaned_at' => now()->subDay(),
            'returned_at' => now(),
        ]);
    }

    /**
     * Pengajuan ditolak pengelola.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_at' => now(),
        ]);
    }

    /**
     * Pengajuan dibatalkan pemohon.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
