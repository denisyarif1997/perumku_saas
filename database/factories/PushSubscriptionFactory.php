<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.$this->faker->uuid().'-'.$this->faker->uuid();

        return [
            'user_id' => User::factory(),
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => rtrim(strtr(base64_encode(random_bytes(65)), '+/', '-_'), '='),
            'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
            'user_agent' => 'TestBrowser/1.0',
        ];
    }
}
