<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Seeders\IdentityDemoSeeder;
use App\Modules\Tenancy\Infrastructure\Seeders\TenancyDemoSeeder;
use Illuminate\Database\Seeder;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $demo = app(TenancyDemoSeeder::class)->seed();

        app(IdentityDemoSeeder::class)->seed($demo);
    }
}
