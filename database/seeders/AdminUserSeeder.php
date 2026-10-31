<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('joz.admin.email');
        $password = config('joz.admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $validator = Validator::make(['password' => $password], ['password' => Password::defaults()]);

        if ($validator->fails()) {
            throw new RuntimeException('ADMIN_PASSWORD is too weak: use at least 12 characters with upper and lower case letters, numbers and symbols.');
        }

        User::query()->create([
            'name' => 'مالك المتجر',
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Owner,
        ]);
    }
}
