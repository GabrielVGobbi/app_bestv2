<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Database\Seeders;

use App\Modules\Playlists\Models\Top;
use Illuminate\Database\Seeder;

class TopSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->tops() as $topData) {
            $top = Top::query()
                ->withTrashed()
                ->updateOrCreate(
                    ['slug' => $topData['slug']],
                    $topData,
                );

            if ($top->trashed()) {
                $top->restore();
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tops(): array
    {
        return [
            $this->top('TOP 1', 'top-1', 1, 10),
            $this->top('TOP 5', 'top-5', 5, 20),
            $this->top('TOP 10', 'top-10', 10, 30),
            $this->top('TOP 20', 'top-20', 20, 40),
            $this->top('Destaque', 'destaque', 1, 50),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function top(string $name, string $slug, int $defaultSlotsQuantity, int $sortOrder): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'default_slots_quantity' => $defaultSlotsQuantity,
            'description' => null,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];
    }
}
