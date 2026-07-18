<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Team;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /** Non-standard factory namespace — modelName() can't guess it, so declare it. */
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'active' => true,
        ];
    }
}
