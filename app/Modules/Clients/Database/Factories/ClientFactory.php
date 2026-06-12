<?php

declare(strict_types=1);

namespace App\Modules\Clients\Database\Factories;

use App\Modules\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => null,
            'responsible_user_id' => null,
            'name' => $this->faker->name(),
            'company_name' => $this->faker->optional()->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('###########'),
            'document' => $this->faker->unique()->numerify('###########'),
            'musical_genre' => $this->faker->optional()->randomElement(['Pop', 'Rock', 'Sertanejo', 'Funk', 'Gospel']),
            'is_agent' => false,
            'notes' => null,
            'classification' => $this->faker->optional()->randomElement(Client::CLASSIFICATIONS),
            'source_id' => $this->faker->optional()->randomElement(['instagram', 'referral', 'site']),
            'status' => 'active',
        ];
    }
}
