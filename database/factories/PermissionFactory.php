<?php

namespace Database\Factories;

use App\Modules\Permissions\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $groups = ['Users', 'Posts', 'Settings', 'Reports'];
        $actions = ['create', 'read', 'update', 'delete', 'export'];
        $group = fake()->randomElement($groups);
        $action = fake()->unique()->randomElement($actions);
        $slug = strtolower($group).'.'.$action;

        return [
            'name' => $action.' '.strtolower($group),
            'slug' => $slug,
            'group' => $group,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
