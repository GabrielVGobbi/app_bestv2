<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Modules\Clients\Database\Seeders\ClientSeeder;
use App\Modules\Companies\Database\Seeders\CompanySeeder;
use App\Modules\Finance\Database\Seeders\FinancialCategorySeeder;
use App\Modules\Finance\Database\Seeders\FinancialEntryDemoSeeder;
use App\Modules\Finance\Database\Seeders\PaymentMethodSeeder;
use App\Modules\Permissions\Database\Seeders\PermissionsSeeder;
use App\Modules\Permissions\Database\Seeders\RolesSeeder;
use App\Modules\Playlists\Database\Seeders\PlaylistSeeder;
use App\Modules\Playlists\Database\Seeders\TopSeeder;
use App\Modules\Services\Database\Seeders\ServiceSeeder;
use App\Modules\Suppliers\Database\Seeders\SupplierSeeder;
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
            SupplierSeeder::class,
            FinancialCategorySeeder::class,
            PaymentMethodSeeder::class,
            ServiceSeeder::class,
            TopSeeder::class,
            PlaylistSeeder::class,
            // PlaylistDeliveryTaskDemoSeeder::class,

            // FinancialEntryDemoSeeder::class,
        ]);
    }
}
