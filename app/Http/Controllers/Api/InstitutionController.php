<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Salon;
use App\Models\User;

/**
 * Panel institucional para directores y coordinadores: métricas globales,
 * seguimiento de docentes y resumen de salones.
 */
class InstitutionController extends Controller
{
    /** Devuelve las estadísticas globales de la institución. */
    public function dashboard()
    {

        $totalSalones = Salon::count();
        $totalTeachers = User::forRole(Role::TEACHER)->where('is_active', true)->count();
        $totalStudents = User::forRole(Role::STUDENT)->where('is_active', true)->count();

        $averageScore = (float) EvaluationResult::where('status', 'completed')->avg('score');

        // Top de salones por resultados de sus estudiantes asignados.
        $topSalones = Salon::query()
            ->select('salones.id', 'salones.grade', 'salones.section')
            ->join('users', 'users.salon_id', '=', 'salones.id')
            ->join('evaluation_results', 'evaluation_results.user_id', '=', 'users.id')
            ->where('evaluation_results.status', 'completed')
            ->groupBy('salones.id', 'salones.grade', 'salones.section')
            ->selectRaw('AVG(evaluation_results.score) as average_score, COUNT(DISTINCT evaluation_results.id) as total_results')
            ->orderByDesc('average_score')
            ->limit(5)
            ->get();

        $stats = [
            'total_salones' => $totalSalones,
            'total_teachers' => $totalTeachers,
            'total_students' => $totalStudents,
            'total_lessons' => Lesson::count(),
            'published_lessons' => Lesson::where('is_published', true)->count(),
            'total_exams' => Exam::count(),
            'active_exams' => Exam::where('is_published', true)->count(),
            'average_score' => round($averageScore, 1),
            'top_salones' => $topSalones,
        ];

        return response()->json(['data' => $stats]);
    }

    /** Lista el personal docente con métricas de su actividad académica. */
    public function teachers()
    {
        $teachers = User::forRole(Role::TEACHER)
            ->where('is_active', true)
            ->select('id', 'full_name', 'email', 'last_login')
            ->orderBy('full_name')
            ->get();

        $lessonCounts = Lesson::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $evaluationCounts = Evaluation::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $examCounts = Exam::selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $data = $teachers->map(function ($teacher) use ($lessonCounts, $evaluationCounts, $examCounts) {
            return [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'email' => $teacher->email,
                'last_login' => $teacher->last_login,
                'total_lessons' => (int) ($lessonCounts[$teacher->id] ?? 0),
                'total_evaluations' => (int) ($evaluationCounts[$teacher->id] ?? 0),
                'total_exams' => (int) ($examCounts[$teacher->id] ?? 0),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** Resumen de salones y estudiantes asignados. */
    public function overview()
    {
        $salones = Salon::withCount('students')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        $data = $salones->map(function ($salon) {
            return [
                'id' => $salon->id,
                'grade' => $salon->grade,
                'section' => $salon->section,
                'display_name' => $salon->display_name ?? ($salon->grade.' - '.$salon->section),
                'total_students' => $salon->students_count,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
