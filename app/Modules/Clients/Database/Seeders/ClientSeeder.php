<?php

declare(strict_types=1);

namespace App\Modules\Clients\Database\Seeders;

use App\Modules\Clients\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::factory()
            ->count(10)
            ->create();
    }
}
