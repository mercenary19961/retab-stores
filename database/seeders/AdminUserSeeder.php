<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Plain password is hashed by the User model's `hashed` cast. Dev creds only.
        User::updateOrCreate(
            ['email' => 'admin@retab.com.sa'],
            [
                'name' => 'Retab Admin',
                'role' => 'admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'locale' => 'ar',
                'admin_theme' => 'light',
            ],
        );

        User::updateOrCreate(
            ['email' => 'editor@retab.com.sa'],
            [
                'name' => 'Retab Editor',
                'role' => 'editor',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
