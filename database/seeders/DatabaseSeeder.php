<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Coordina la carga principal de roles, cuentas y datos funcionales de prueba.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta los seeders de la aplicación en el orden requerido por sus dependencias.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdminUserSeeder::class,
            TestDataSeeder::class,
            MathContentSeeder::class,
            UnsaacLessonSeeder::class,
            DniSeeder::class,
            ExamSeeder::class,
            RankingSeeder::class,
            GamificationSeeder::class,
        ]);
    }
}