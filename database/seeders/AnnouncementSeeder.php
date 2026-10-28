<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Publica anuncios globales de muestra para poblar la sección de novedades.
 */
class AnnouncementSeeder extends Seeder
{
    /**
     * Crea anuncios activos e inactivos de demostración.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        $announcements = [
            [
                'title' => 'Bienvenido al nuevo año escolar',
                'body' => 'Te damos la bienvenida a Aulamate. Explora las lecciones de matemáticas de tu grado, completa las evaluaciones y sigue tu progreso desde tu panel. ¡Empieza hoy con tu primera lección!',
                'type' => 'info',
                'is_active' => true,
            ],
            [
                'title' => 'Nuevas lecciones de Trigonometría',
                'body' => 'Ya están disponibles las lecciones de razones trigonométricas, ley de senos y ley de cosenos para los grados superiores. Ingresa a la sección de lecciones para conocerlas.',
                'type' => 'success',
                'is_active' => true,
            ],
            [
                'title' => 'Mantenimiento programado del sistema',
                'body' => 'La plataforma estará en mantenimiento el sábado de 2:00 a 6:00 a. m. Durante ese tiempo el acceso a lecciones y evaluaciones podría interrumpirse brevemente.',
                'type' => 'warning',
                'is_active' => true,
            ],
            [
                'title' => 'Olimpiada Nacional de Matemática',
                'body' => 'Las inscripciones para la Olimpiada Nacional de Matemática se abrirán el próximo mes. Los interesados pueden inscribirse con su docente de matemáticas.',
                'type' => 'info',
                'is_active' => false,
            ],
        ];

        foreach ($announcements as $data) {
            Announcement::create([
                'id' => Str::uuid(),
                'title' => $data['title'],
                'body' => $data['body'],
                'type' => $data['type'],
                'is_active' => $data['is_active'],
                'created_by' => $admin?->id,
            ]);
        }

        $this->command->info('  4 anuncios creados (3 activos, 1 inactivo)');
    }
}
