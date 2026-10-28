<?php

namespace Database\Seeders;

use App\Services\GamificationService;
use Illuminate\Database\Seeder;

/**
 * Sincroniza las definiciones de logros y recompensas de gamificación.
 */
class GamificationSeeder extends Seeder
{
    /**
     * Delega la sincronización del catálogo al servicio de gamificación.
     */
    public function run(): void
    {
        GamificationService::syncDefinitions();
    }
}
