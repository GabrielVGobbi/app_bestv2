<?php

namespace Database\Factories;

use App\Models\Export;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    protected $model = Export::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'model_class' => User::class,
            'status' => Export::STATUS_PENDING,
            'format' => 'csv',
            'columns' => ['name', 'email'],
            'filters' => [],
            'search' => null,
            'sort_column' => null,
            'sort_direction' => null,
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'total_records' => null,
            'processed_records' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Export::STATUS_COMPLETED,
            'file_path' => 'exports/test-export.csv',
            'file_name' => 'export-test.csv',
            'file_size' => 1024,
            'total_records' => 10,
            'processed_records' => 10,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Export::STATUS_FAILED,
            'error_message' => 'Something went wrong.',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Export::STATUS_COMPLETED,
            'file_path' => 'exports/test-export.csv',
            'file_name' => 'export-test.csv',
            'file_size' => 1024,
            'total_records' => 10,
            'processed_records' => 10,
            'completed_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
    }
}
