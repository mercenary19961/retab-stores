<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            ContentPageSeeder::class,
            AdminUserSeeder::class,
            CatalogSeeder::class,
            ClientReviewSeeder::class,
        ]);
    }
}
