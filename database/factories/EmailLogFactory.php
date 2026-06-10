<?php

namespace Database\Factories;

use App\Modules\Email\Enums\EmailCategory;
use App\Modules\Email\Enums\EmailStatus;
use App\Modules\Email\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        return [
            'to_email' => $this->faker->safeEmail(),
            'to_name' => $this->faker->name(),
            'from_email' => config('mail.from.address', 'noreply@example.com'),
            'from_name' => config('mail.from.name', 'App'),
            'subject' => $this->faker->sentence(4),
            'template' => 'emails.exports.ready',
            'variables' => null,
            'category' => $this->faker->randomElement(EmailCategory::cases())->value,
            'status' => EmailStatus::Sent->value,
            'error_message' => null,
            'attempts' => 1,
            'mailer' => 'log',
            'message_id' => null,
            'idempotency_key' => $this->faker->uuid(),
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => EmailStatus::Failed->value,
            'error_message' => 'Connection refused',
            'attempts' => 3,
            'failed_at' => now(),
        ]);
    }

    public function queued(): static
    {
        return $this->state([
            'status' => EmailStatus::Queued->value,
            'attempts' => 0,
        ]);
    }
}
