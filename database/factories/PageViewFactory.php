<?php

namespace Database\Factories;

use App\Models\HousingEstate;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Path memakai rute yang benar-benar ada supaya data uji tidak bergantung
     * pada path rekaan. Kunjungan dicatat tanpa user (tamu) secara default;
     * pakai state forUser() untuk kunjungan seorang pengguna.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'housing_estate_id' => null,
            'path' => '/admin/dashboard',
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Kunjungan milik seorang pengguna tertentu (mengisi user_id).
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'housing_estate_id' => $user->housing_estate_id,
        ]);
    }

    /**
     * Kunjungan pada halaman tertentu.
     */
    public function onPath(string $path): static
    {
        return $this->state(fn (array $attributes) => ['path' => $path]);
    }

    /**
     * Kunjungan milik condominan tertentu.
     */
    public function forEstate(int|HousingEstate $estate): static
    {
        return $this->state(fn (array $attributes) => [
            'housing_estate_id' => $estate instanceof HousingEstate ? $estate->id : $estate,
        ]);
    }
}
