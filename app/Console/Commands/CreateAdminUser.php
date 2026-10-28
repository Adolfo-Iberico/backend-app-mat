<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea interactivamente una cuenta administrativa y la asocia al rol admin.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'aulamate:create-admin
        {--email= : Email del administrador}
        {--name= : Nombre completo}
        {--password= : Contraseña (si no se provee, se genera una aleatoria)}';

    protected $description = 'Crear un usuario administrador para Aulamate';

    /**
     * Valida los datos proporcionados, crea el usuario y muestra sus datos básicos.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?? $this->ask('Email del administrador');
        $name = $this->option('name') ?? $this->ask('Nombre completo', 'Administrador');
        $password = $this->option('password') ?? $this->secret('Contraseña');

        if (User::where('email', $email)->exists()) {
            $this->error("El email {$email} ya está registrado.");
            return 1;
        }

        $user = User::create([
            'id' => Str::uuid(),
            'email' => $email,
            'full_name' => $name,
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'provider' => 'email'
        ]);

        $this->info("Administrador creado exitosamente:");
        $this->line("  Email:    {$email}");
        $this->line("  Nombre:   {$name}");
        $this->line("  ID:       {$user->id}");

        return 0;
    }
}
