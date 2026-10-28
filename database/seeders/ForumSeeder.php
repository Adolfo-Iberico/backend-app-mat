<?php

namespace Database\Seeders;

use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea hilos de foro de muestra con comentarios sobre lecciones publicadas.
 */
class ForumSeeder extends Seeder
{
    /**
     * Genera hilos del primer docente con dudas y respuestas de estudiantes.
     */
    public function run(): void
    {
        // Idempotente: no duplicar hilos si ya existen datos de foro.
        if (ForumThread::exists()) {
            return;
        }

        $teacher = User::where('email', 'profesor1@mathflow.com')->first();
        $students = User::where('email', 'like', 'estudiante%@mathflow.com')
            ->orderBy('email')
            ->take(3)
            ->get();

        if (! $teacher || $students->isEmpty()) {
            return;
        }

        $lesson1 = Lesson::where('title', 'Ecuaciones Lineales')->first();
        $lesson2 = Lesson::where('title', 'Áreas de Polígonos')->first();
        if (! $lesson2) {
            $lesson2 = Lesson::where('title', 'Areas de Poligonos')->first();
        }
        $lesson3 = Lesson::where('title', 'Funciones y sus Gráficas')->first();

        $threads = [
            [
                'lesson' => $lesson1,
                'title' => 'Dudas sobre ecuaciones lineales',
                'body' => 'Este hilo es para resolver sus preguntas sobre ecuaciones de primer grado. No duden en comentar.',
                'posts' => [
                    ['user' => $students[0], 'body' => 'Profesor, ¿por qué al pasar el 5 al otro lado se vuelve negativo?'],
                    ['user' => $teacher, 'body' => 'Porque al restar en ambos miembros de la igualdad cambia de signo. Revisa el paso a paso del ejemplo de la lección.'],
                ],
            ],
            [
                'lesson' => $lesson2,
                'title' => 'Foro de áreas y perímetros',
                'body' => 'Compartan aquí sus ejercicios de áreas de polígonos y sus dudas sobre las fórmulas.',
                'posts' => [
                    ['user' => $students->count() > 1 ? $students[1] : $students[0], 'body' => '¿La fórmula del trapecio también sirve para paralelogramos?'],
                ],
            ],
            [
                'lesson' => $lesson3,
                'title' => 'Funciones: práctica y consultas',
                'body' => 'Espacio de consulta sobre funciones y sus gráficas. Pueden subir sus intentos de solución.',
                'posts' => [],
            ],
        ];

        foreach ($threads as $data) {
            if (! $data['lesson']) {
                continue;
            }

            $thread = ForumThread::create([
                'id' => Str::uuid(),
                'teacher_id' => $teacher->id,
                'author_id' => $teacher->id,
                'lesson_id' => $data['lesson']->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'status' => ForumThread::STATUS_OPEN,
            ]);

            foreach ($data['posts'] as $post) {
                ForumPost::create([
                    'id' => Str::uuid(),
                    'thread_id' => $thread->id,
                    'user_id' => $post['user']->id,
                    'body' => $post['body'],
                ]);
            }
        }

        $this->command->info('  3 hilos de foro creados con comentarios de muestra');
    }
}
