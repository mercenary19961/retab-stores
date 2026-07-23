<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('retab.admin.email');
        $password = config('retab.admin.password');

        // Never seed a known-password admin into production: require a real
        // ADMIN_PASSWORD there. Dev/local keep the convenience default + editor.
        if (app()->environment('production')) {
            if (blank($password)) {
                $this->command?->warn('AdminUserSeeder: set ADMIN_EMAIL + ADMIN_PASSWORD before seeding in production — admin not created.');

                return;
            }
        } else {
            $password ??= 'password';
            $this->seedEditor();
        }

        // Plain password is hashed by the User model's `hashed` cast.
        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Retab Admin',
                'role' => 'admin',
                'password' => $password,
                'email_verified_at' => now(),
                'locale' => 'ar',
                'admin_theme' => 'light',
            ],
        );
    }

    private function seedEditor(): void
    {
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
