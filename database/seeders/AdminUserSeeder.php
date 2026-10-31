<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
        $adminRole = Role::where('name', 'admin')->first();

        User::updateOrCreate(
            ['email' => 'admin@mathflow.com'],
            [
                'id' => Str::uuid(),
                'full_name' => 'Administrador Aulamate',
                'password' => Hash::make('admin123456'),
                'role_id' => $adminRole?->id,
                'is_active' => true,
                'provider' => 'email',
            ]
        );
    }
}
