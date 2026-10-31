<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;

/** Consulta resultados de evaluaciones y calcula métricas para informes docentes. */
class GradesPDFExport
{
    protected $teacherId;

    protected $filters;

    /** Configura el informe con un docente opcional y filtros de consulta. */
    public function __construct($teacherId = null, $filters = [])
    {
        $this->teacherId = $teacherId;
        $this->filters = $filters;
    }

    /** Obtiene resultados con sus datos de estudiante, evaluación y lección. */
    public function getData()
    {
        $query = DB::table('evaluation_results')
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->join('users', 'evaluation_results.user_id', '=', 'users.id')
            ->leftJoin('lessons', 'evaluations.lesson_id', '=', 'lessons.id')
            ->select(
                'users.full_name as student_name',
                'users.email as student_email',
                'evaluations.title as evaluation_title',
                'evaluations.type as evaluation_type',
                'lessons.title as lesson_title',
                'lessons.unit as area',
                'evaluation_results.score',
                'evaluation_results.total_questions',
                'evaluation_results.correct_answers',
                'evaluation_results.created_at as completed_at'
            );

        if ($this->teacherId) {
            $query->where('evaluations.teacher_id', $this->teacherId);
        }

        if (! empty($this->filters['student_id'])) {
            $query->where('evaluation_results.user_id', $this->filters['student_id']);
        }

        if (! empty($this->filters['evaluation_id'])) {
            $query->where('evaluation_results.evaluation_id', $this->filters['evaluation_id']);
        }

        if (! empty($this->filters['area'])) {
            $query->where('lessons.unit', $this->filters['area']);
        }

        return $query->orderBy('evaluation_results.created_at', 'desc')->get();
    }

    /** Resume puntajes por área y tipo, e identifica a los cinco estudiantes con mayor promedio. */
    public function getSummary()
    {
        $data = $this->getData();

        $summary = [
            'total_results' => $data->count(),
            'average_score' => $data->avg('score') ?? 0,
            'max_score' => $data->max('score') ?? 0,
            'min_score' => $data->min('score') ?? 0,
            'by_area' => $data->groupBy('area')->map(function ($items, $area) {
                return [
                    'area' => $area,
                    'count' => $items->count(),
                    'average' => $items->avg('score'),
                ];
            })->values(),
            'by_type' => $data->groupBy('evaluation_type')->map(function ($items, $type) {
                return [
                    'type' => $type,
                    'count' => $items->count(),
                    'average' => $items->avg('score'),
                ];
            })->values(),
            'top_students' => $data->groupBy('student_name')->map(function ($items, $name) {
                return [
                    'student_name' => $name,
                    'count' => $items->count(),
                    'average' => $items->avg('score'),
                ];
            })->sortByDesc('average')->take(5)->values(),
        ];

        return $summary;
    }

    /** Convierte el puntaje a la escala AD/A/B/C usada en los informes. */
    public static function gradeLetter($score): string
    {
        $score = (float) $score;
        if ($score >= 18) {
            return 'AD';
        }
        if ($score >= 15) {
            return 'A';
        }
        if ($score >= 12) {
            return 'B';
        }

        return 'C';
    }
}
