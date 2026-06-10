<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Database\Factories;

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Timeline\Models\TimelineComment;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimelineCommentFactory extends Factory
{
    protected $model = TimelineComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $client = Client::factory()->create();

        return [
            'commentable_type' => Client::class,
            'commentable_id' => $client->id,
            'user_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'body_format' => 'plain',
        ];
    }
}
