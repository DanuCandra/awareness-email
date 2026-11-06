<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) config('initial-admin.email')));
        $username = Str::lower(trim((string) config('initial-admin.username')));

        if ($email === '' || $username === '') {
            throw new RuntimeException('Konfigurasi akun admin awal belum lengkap.');
        }

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        if (User::query()->where('username', $username)->exists()) {
            throw new RuntimeException('Username admin awal sudah dipakai akun lain.');
        }

        $password = (string) config('initial-admin.password');

        if ($password === '') {
            throw new RuntimeException('Password admin awal belum diberikan.');
        }

        User::create([
            'name' => config('initial-admin.name'),
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ]);
    }
}
