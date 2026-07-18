<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Company;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /** Non-standard factory namespace — modelName() can't guess it, so declare it. */
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'legal_name' => null,
            'currency_code' => 'USD',
            'country_code' => null,
            'timezone' => null,
            'is_default' => false,
            'active' => true,
        ];
    }
}
