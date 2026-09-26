<?php

namespace Database\Factories;

use App\Models\HousingEstate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Akun hasil factory otomatis terikat ke HousingEstate pertama yang ada,
     * meniru kondisi produksi. Gunakan state withoutEstate() untuk menguji
     * akun yang sengaja tidak terikat perumahan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'housing_estate_id' => HousingEstate::query()->value('id'),
        ];
    }

    /**
     * Akun tanpa housing_estate_id (staf platform / belum terikat).
     */
    public function withoutEstate(): static
    {
        return $this->state(fn (array $attributes) => [
            'housing_estate_id' => null,
        ]);
    }

    /**
     * Akun terikat ke HousingEstate tertentu.
     */
    public function forEstate(int|HousingEstate $estate): static
    {
        return $this->state(fn (array $attributes) => [
            'housing_estate_id' => $estate instanceof HousingEstate ? $estate->id : $estate,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
