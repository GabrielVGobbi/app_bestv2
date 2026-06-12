<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Modules\Clients\Database\Seeders\ClientSeeder;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Services\Database\Seeders\ServiceSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            UserSeeder::class,

            CompanySeeder::class,
            ClientSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
