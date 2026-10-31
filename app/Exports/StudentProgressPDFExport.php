<?php

namespace App\Exports;

use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Reúne el avance en lecciones y los resultados de evaluación de un estudiante. */
class StudentProgressPDFExport
{
    protected $studentId;

    /** Selecciona el estudiante cuyos datos se prepararán para el informe. */
    public function __construct($studentId)
    {
        $this->studentId = $studentId;
    }

    /** Devuelve estudiante, lecciones, evaluaciones y estadísticas de progreso. */
    public function getData()
    {
        $student = User::find($this->studentId);

        $lessons = LessonProgress::where('lesson_progress.user_id', $this->studentId)
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->select('lessons.title', 'lessons.unit', 'lesson_progress.status', 'lesson_progress.progress', 'lesson_progress.updated_at')
            ->orderBy('lessons.unit')
            ->get();

        $evaluations = DB::table('evaluation_results')
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->join('users', 'evaluation_results.user_id', '=', 'users.id')
            ->select('evaluations.title', 'evaluation_results.score', 'evaluation_results.correct_answers', 'evaluation_results.total_questions', 'evaluation_results.created_at')
            ->where('evaluation_results.user_id', $this->studentId)
            ->orderBy('evaluation_results.created_at', 'desc')
            ->get();

        // Estructura de resultados de evaluaciones (compatible con el CSV student)
        $results = $evaluations->map(function ($e) {
            $date = $e->created_at ?? '';
            if ($date && ! is_string($date) && method_exists($date, 'format')) {
                $date = $date->format('d/m/Y H:i');
            }

            return [
                'evaluation_name' => $e->title,
                'type' => 'evaluation',
                'score' => $e->score,
                'completed_at' => $date,
            ];
        })->all();

        return [
            'student' => $student,
            'lessons' => $lessons,
            'evaluations' => $evaluations,
            'results' => $results,
            'average' => $evaluations->avg('score') ?? 0,
            'stats' => [
                'lessons_completed' => $lessons->where('status', 'completed')->count(),
                'lessons_in_progress' => $lessons->where('status', 'in_progress')->count(),
                'average_score' => $evaluations->avg('score') ?? 0,
                'total_evaluations' => $evaluations->count(),
            ],
        ];
    }
}
