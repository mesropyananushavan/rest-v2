<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Tenancy\Infrastructure\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'default_locale' => 'hy',
            'currency' => 'AMD',
            'status' => 'active',
        ];
    }
}
