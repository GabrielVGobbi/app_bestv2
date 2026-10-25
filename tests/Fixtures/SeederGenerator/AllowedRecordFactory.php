<?php

declare(strict_types=1);

namespace Tests\Fixtures\SeederGenerator;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AllowedRecord> */
class AllowedRecordFactory extends Factory
{
    protected $model = AllowedRecord::class;

    /** @return array{label: string, unique_key: string} */
    public function definition(): array
    {
        return [
            'label' => 'Synthetic fixture',
            'unique_key' => 'fixture-'.bin2hex(random_bytes(8)),
        ];
    }
}
