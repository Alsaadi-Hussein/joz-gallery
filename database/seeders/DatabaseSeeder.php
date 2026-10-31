<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Every seeder is safe to run again: it adds
     * what is missing and leaves anything edited in the panel alone.
     */
    public function run(): void
    {
        $this->call([
            GovernorateSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            AdminUserSeeder::class,
        ]);

        if (config('joz.seed_demo')) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
