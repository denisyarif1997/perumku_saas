<?php

namespace Database\Factories;

use App\Models\HousingEstate;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Barang hasil factory otomatis terikat ke HousingEstate pertama yang ada,
     * meniru kondisi produksi. Gunakan state forSpecificEstate() untuk menguji
     * isolasi data antar-perumahan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'housing_estate_id' => HousingEstate::query()->value('id'),
            'name' => fake()->randomElement([
                'Kursi Lipat', 'Tenda Panggung', 'Kursi Pesta', 'Mixer Suara',
                'Proyektor', 'Papanstacle', 'Tangga Lipat', 'Kotak P3K',
            ]),
            'code' => strtoupper(fake()->unique()->bothify('INV-####')),
            'description' => fake()->optional()->sentence(),
            'quantity' => fake()->numberBetween(1, 20),
            'unit' => fake()->randomElement(['unit', 'buah', 'set', 'lembar']),
            'location' => fake()->randomElement(['Gudang A', 'Ruang Multimedia', 'Pos Security', 'Ruang Kelas']),
            'status' => 'available',
        ];
    }

    /**
     * Barang yang tidak boleh dipinjam (perawatan atau tidak dipakai lagi).
     */
    public function notLoanable(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => fake()->randomElement(['maintenance', 'retired']),
        ]);
    }

    /**
     * Barang yang sudah habis dipinjam.
     */
    public function fullyBorrowed(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 1,
        ]);
    }
}
