<?php

declare(strict_types=1);

namespace App\Modules\Services\Database\Seeders;

use App\Modules\Services\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->services() as $serviceData) {
            $service = Service::query()
                ->withTrashed()
                ->updateOrCreate(
                    ['slug' => $serviceData['slug']],
                    $serviceData,
                );

            if ($service->trashed()) {
                $service->restore();
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function services(): array
    {
        return [
            $this->service('Ads', 'ads'),
            $this->service('Curso', 'curso'),
            $this->service('Ebook', 'ebook'),
            $this->service('Selo', 'selo'),
            $this->service('Venda de ativo', 'venda-de-ativo'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function service(string $name, string $slug): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'base_price' => 0,
            'currency' => 'BRL',
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
