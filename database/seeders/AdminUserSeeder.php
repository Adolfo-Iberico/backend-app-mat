<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Garantiza la existencia de la cuenta administrativa inicial de Aulamate.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Crea o actualiza el usuario administrador predeterminado y su rol.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@mathflow.com'],
            [
                'id' => Str::uuid(),
                'full_name' => 'Administrador Aulamate',
                'password' => 'admin123456',
                'role' => 'admin',
                'is_active' => true,
                'provider' => 'email',
            ]
        );
    }
}
