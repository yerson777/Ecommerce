<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;

class UsuarioAdminSeeder extends \Illuminate\Database\Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@everly.local');
        $password = env('ADMIN_PASSWORD') ?: Str::password(12, true, true);

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'password' => \Illuminate\Support\Facades\Hash::make($password),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Usuario admin creado. Contraseña temporal: {$password}");
        }
    }
}