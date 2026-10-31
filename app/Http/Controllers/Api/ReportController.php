<?php

namespace App\Http\Controllers\Api;

use App\Exports\GradesExport;
use App\Exports\GradesPDFExport;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\SubmittedWork;
use App\Models\User;
use App\Services\StudentReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Genera informes y exportaciones de rendimiento, evaluaciones, estudiantes y participación.
 */
class ReportController extends Controller
{
    /**
     * Reporte de rendimiento general
     */
    public function performanceReport(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:week,month,quarter,year,current,last_month,last_quarter,all_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $query = EvaluationResult::query();

        // Filtros de fecha
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        } elseif ($request->has('period') && $request->period !== 'all_time') {
            $query->whereBetween('created_at', $this->getPeriodDateRange($request->period));
        }

        // Si es docente, solo ver resultados de sus propias evaluaciones
        if (Auth::user()->isScopedTeacher()) {
            $evaluationIds = Evaluation::where('teacher_id', Auth::id())->pluck('id');
            $query->whereIn('evaluation_id', $evaluationIds);
        }

        // Estadísticas: 4 agregados escalares en una sola query (antes 4 queries por separado)
        $aggregate = (clone $query)
            ->selectRaw('COUNT(*) as total, AVG(score) as avg_score, COUNT(DISTINCT user_id) as distinct_users, SUM(CASE WHEN score >= 12 THEN 1 ELSE 0 END) as passing')
            ->first();

        $total = (int) ($aggregate->total ?? 0);

        $stats = [
            'total_evaluations' => $total,
            'average_score' => $aggregate->avg_score,
            'total_students' => (int) ($aggregate->distinct_users ?? 0),
            'passing_rate' => $total > 0 ? round(((int) ($aggregate->passing ?? 0) / $total) * 100, 2) : 0,
            'top_performers' => $this->getTopPerformers(clone $query),
            'difficulty_areas' => $this->getDifficultyAreas(clone $query),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Reporte de calificaciones
     */
    public function gradesReport(Request $request)
    {
        $request->validate([
            'evaluation_id' => 'nullable|exists:evaluations,id',
            'student_id' => 'nullable|exists:users,id',
            'period' => 'nullable|in:week,month,quarter,year,current,last_month,last_quarter,all_time',
        ]);

        $query = $this->buildGradesQuery($request);

        $results = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 20), 50));

        // Resumen estadístico (recalculado sin paginar)
        $summaryQuery = $this->buildGradesQuery($request);
        $summary = [
            'average' => $summaryQuery->avg('score'),
            'max' => $summaryQuery->max('score'),
            'min' => $summaryQuery->min('score'),
            'total' => $summaryQuery->count(),
        ];

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'total' => $results->total(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Reporte de un estudiante específico
     */
    public function studentReport($userId)
    {
        $user = User::with(['studentProfile'])->findOrFail($userId);

        if (Auth::user()->isScopedTeacher()) {
            $evaluationIds = Evaluation::where('teacher_id', Auth::id())->pluck('id');
            $hasResults = EvaluationResult::where('user_id', $userId)
                ->whereIn('evaluation_id', $evaluationIds)
                ->exists();

            if (! $hasResults) {
                return response()->json([
                    'message' => __('report_no_access_student_data'),
                ], 403);
            }
        }

        $lessonProgress = LessonProgress::where('user_id', $userId)
            ->with('lesson')
            ->get();

        $evaluationResults = EvaluationResult::where('user_id', $userId)
            ->with('evaluation')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total_lessons_completed' => $lessonProgress->where('status', 'completed')->count(),
            'total_lessons_in_progress' => $lessonProgress->where('status', 'in_progress')->count(),
            'average_score' => $evaluationResults->avg('score') ?? 0,
            'total_evaluations' => $evaluationResults->count(),
            'best_score' => $evaluationResults->max('score') ?? 0,
            'worst_score' => $evaluationResults->min('score') ?? 0,
            'current_streak' => $user->studentProfile->current_streak ?? 0,
            'badges' => $user->studentProfile->badges ?? [],
        ];

        $strengths = $this->analyzeStrengths($evaluationResults);

        return response()->json([
            'student' => $user,
            'stats' => $stats,
            'lesson_progress' => $lessonProgress,
            'evaluation_results' => $evaluationResults,
            'strengths' => $strengths,
        ]);
    }

    /**
     * Exportar reporte en PDF
     */
    public function exportPDF(Request $request)
    {
        $rows = $this->buildGradesQuery($request)
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'average' => $rows->avg('score'),
            'max' => $rows->max('score'),
            'min' => $rows->min('score'),
            'total' => $rows->count(),
        ];

        $pdf = Pdf::loadView('reports.report-pdf', [
            'rows' => $rows,
            'summary' => $summary,
            'period' => $request->period ?? 'current',
        ]);

        return $pdf->download('reporte-rendimiento-'.($request->period ?? 'current').'.pdf');
    }

    /**
     * Exportar reporte en Excel
     */
    public function exportExcel(Request $request)
    {
        $rows = $this->buildGradesQuery($request)
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'reporte-rendimiento-'.($request->period ?? 'current').'.xlsx';

        return Excel::download(new GradesExport($rows), $filename);
    }

    /**
     * Exportar reporte de rendimiento en PDF
     */
    public function exportPerformancePDF(Request $request)
    {
        $teacherId = Auth::user()->isScopedTeacher() ? Auth::id() : null;
        $filters = array_filter([
            'student_id' => $request->student_id,
            'evaluation_id' => $request->evaluation_id,
            'area' => $request->area,
        ]);

        $export = new GradesPDFExport($teacherId, $filters);
        $data = $export->getData();
        $summary = $export->getSummary();

        $html = $this->buildPerformanceHTML($data, $summary);

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->download('reporte-rendimiento.pdf');
    }

    /**
     * Exportar reporte de rendimiento en Excel
     */
    public function exportPerformanceExcel(Request $request)
    {
        $teacherId = Auth::user()->isScopedTeacher() ? Auth::id() : null;
        $filters = array_filter([
            'student_id' => $request->student_id,
            'evaluation_id' => $request->evaluation_id,
            'area' => $request->area,
        ]);

        $lang = $request->language ?? 'es';

        return Excel::download(new GradesExport(
            (new GradesPDFExport($teacherId, $filters))->getData(),
            $lang
        ), 'reporte-rendimiento.xlsx');
    }

    /**
     * Exportar reporte de un estudiante en PDF
     */
    public function exportStudentReportPDF(Request $request, $studentId)
    {
        User::findOrFail($studentId);

        return app(StudentReportService::class)->pdf($studentId);
    }

    /**
     * Exportar reporte de un estudiante en Excel
     */
    public function exportStudentReportExcel(Request $request, $studentId)
    {
        User::findOrFail($studentId);

        return app(StudentReportService::class)->excel($studentId);
    }

    /**
     * Exportar todas las calificaciones en PDF
     */
    public function exportGradesPDF(Request $request)
    {
        $teacherId = Auth::user()->isScopedTeacher() ? Auth::id() : null;
        $filters = array_filter([
            'student_id' => $request->student_id,
            'evaluation_id' => $request->evaluation_id,
            'area' => $request->area,
        ]);

        $export = new GradesPDFExport($teacherId, $filters);
        $data = $export->getData();
        $summary = $export->getSummary();

        $html = $this->buildGradesHTML($data, $summary);

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->download('calificaciones.pdf');
    }

    /**
     * Exportar todas las calificaciones en Excel
     */
    public function exportGradesExcel(Request $request)
    {
        $teacherId = Auth::user()->isScopedTeacher() ? Auth::id() : null;
        $filters = array_filter([
            'student_id' => $request->student_id,
            'evaluation_id' => $request->evaluation_id,
            'area' => $request->area,
        ]);

        $lang = $request->language ?? 'es';

        return Excel::download(new GradesExport(
            (new GradesPDFExport($teacherId, $filters))->getData(),
            $lang
        ), 'calificaciones.xlsx');
    }

    /**
     * Exportar reporte individual de un estudiante en CSV.
     */
    public function exportStudentReportCSV(Request $request, $studentId)
    {
        User::findOrFail($studentId);

        return app(StudentReportService::class)->csv($studentId);
    }

    /**
     * Exportar todas las calificaciones en CSV.
     */
    public function exportGradesCSV(Request $request)
    {
        $teacherId = Auth::user()->isScopedTeacher() ? Auth::id() : null;
        $filters = array_filter([
            'student_id' => $request->student_id,
            'evaluation_id' => $request->evaluation_id,
            'area' => $request->area,
        ]);

        $data = (new GradesPDFExport($teacherId, $filters))->getData();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="calificaciones-'.now()->format('Y-m-d_His').'.csv"',
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                __('csv_header_student'),
                __('csv_header_evaluation'),
                __('csv_header_type'),
                __('csv_header_score'),
                __('csv_header_letter'),
            ]);

            foreach (($data['rows'] ?? $data ?? []) as $row) {
                $row = (array) $row;
                fputcsv($handle, [
                    $this->sanitizeCsvValue($row['student_name'] ?? $row['full_name'] ?? ''),
                    $this->sanitizeCsvValue($row['evaluation_name'] ?? ''),
                    $this->sanitizeCsvValue($row['type'] ?? ''),
                    $row['score'] ?? '',
                    $this->sanitizeCsvValue($row['letter'] ?? ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function sanitizeCsvValue(string $value): string
    {
        $dangerousPrefixes = ['=', '+', '-', '@', "\t", "\r"];
        foreach ($dangerousPrefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $value = "'".$value;
                break;
            }
        }

        return $value;
    }

    // ========== HTML BUILDERS ==========

    /**
     * Enhanced performance report with filters.
     */
    public function filteredPerformanceReport(Request $request)
    {
        $request->validate([
            'unit' => 'nullable|string',
            'student_id' => 'nullable|exists:users,id',
            'teacher_id' => 'nullable|exists:users,id',
            'period' => 'nullable|in:week,month,quarter,year,current,last_month,last_quarter,all_time',
        ]);

        $evalQuery = EvaluationResult::with(['user', 'evaluation.lesson'])
            ->where('status', 'completed');

        $workQuery = SubmittedWork::with(['student', 'lesson', 'evaluation'])
            ->where('status', 'graded');

        if (Auth::user()->isScopedTeacher()) {
            $teacherEvalIds = $this->teacherScopedEvaluationIds();
            $teacherLessonIds = $this->teacherScopedLessonIds();
            $evalQuery->whereIn('evaluation_id', $teacherEvalIds);
            $workQuery->where(function ($q) use ($teacherLessonIds) {
                $q->whereIn('lesson_id', $teacherLessonIds)
                    ->orWhereIn('evaluation_id', $this->teacherScopedEvaluationIds());
            });
        }

        if ($request->filled('student_id')) {
            $evalQuery->where('user_id', $request->student_id);
            $workQuery->where('student_id', $request->student_id);
        }

        if ($request->filled('unit')) {
            $evalQuery->whereHas('evaluation.lesson', fn ($q) => $q->where('unit', $request->unit));
            $workQuery->whereHas('lesson', fn ($q) => $q->where('unit', $request->unit));
        }

        if ($request->filled('teacher_id')) {
            $teacherEvaluationIds = Evaluation::where('teacher_id', $request->teacher_id)->pluck('id');
            $teacherLessonIds = Lesson::where('teacher_id', $request->teacher_id)->pluck('id');
            $evalQuery->whereIn('evaluation_id', $teacherEvaluationIds);
            $workQuery->whereIn('lesson_id', $teacherLessonIds);
        }

        if ($request->filled('period') && $request->period !== 'all_time') {
            $range = $this->getPeriodDateRange($request->period);
            $evalQuery->whereBetween('created_at', $range);
            $workQuery->whereBetween('created_at', $range);
        }

        $evalResults = $evalQuery->get();
        $works = $workQuery->get();

        $studentGroups = $evalResults->groupBy('user_id')->map(function ($results) use ($works) {
            $studentId = $results->first()->user_id;
            $studentWorks = $works->where('student_id', $studentId);

            return [
                'student' => $results->first()->user,
                'evaluations' => [
                    'count' => $results->count(),
                    'average' => round($results->avg('score'), 2),
                ],
                'submitted_works' => [
                    'count' => $studentWorks->count(),
                    'average' => $studentWorks->count() > 0 ? round($studentWorks->avg('score'), 2) : null,
                ],
                'overall_average' => round(
                    collect([$results->avg('score'), $studentWorks->avg('score')])
                        ->filter()
                        ->avg(),
                    2
                ),
            ];
        })->sortByDesc('overall_average')->values();

        return response()->json([
            'data' => $studentGroups,
            'summary' => [
                'total_students' => $studentGroups->count(),
                'class_average' => $studentGroups->count() > 0
                    ? round($studentGroups->avg('overall_average'), 2)
                    : null,
            ],
        ]);
    }

    /**
     * Detailed student report across all courses.
     */
    public function studentDetailReport(Request $request, $studentId)
    {
        $student = User::findOrFail($studentId);

        $teacherEvalIds = $this->teacherScopedEvaluationIds();
        $teacherLessonIds = $this->teacherScopedLessonIds();

        $areas = ['Álgebra', 'Geometría', 'Trigonometría'];
        $courses = [];

        foreach ($areas as $area) {
            $evalResults = EvaluationResult::where('user_id', $studentId)
                ->where('status', 'completed')
                ->when($teacherEvalIds, fn ($q) => $q->whereIn('evaluation_id', $teacherEvalIds))
                ->whereHas('evaluation.lesson', fn ($q) => $q->where('unit', $area))
                ->get();

            $works = SubmittedWork::where('student_id', $studentId)
                ->where('status', 'graded')
                ->where(function ($q) use ($area) {
                    $q->whereHas('lesson', fn ($lq) => $lq->where('unit', $area))
                        ->orWhereHas('evaluation.lesson', fn ($lq) => $lq->where('unit', $area));
                })
                ->when($teacherLessonIds, fn ($q) => $q->whereIn('lesson_id', $teacherLessonIds))
                ->get();

            if ($evalResults->isEmpty() && $works->isEmpty()) {
                continue;
            }

            $allScores = $evalResults->pluck('score')->merge($works->pluck('score'));
            $average = $allScores->count() > 0 ? round($allScores->avg(), 2) : null;

            // Ranking within this area
            $allStudentAvgs = DB::table('evaluation_results')
                ->where('status', 'completed')
                ->whereHas('evaluation.lesson', fn ($q) => $q->where('unit', $area))
                ->select('user_id', DB::raw('AVG(score) as avg'))
                ->groupBy('user_id')
                ->get();

            $rank = $allStudentAvgs->where('avg', '>', $average ?? 0)->count() + 1;

            $courses[] = [
                'area' => $area,
                'evaluations_count' => $evalResults->count(),
                'submitted_works_count' => $works->count(),
                'average' => $average,
                'ranking_position' => $rank,
            ];
        }

        $allScores = collect();
        foreach ($courses as $c) {
            if ($c['average'] !== null) {
                $allScores->push($c['average']);
            }
        }

        return response()->json([
            'student' => $student,
            'courses' => $courses,
            'overall_average' => $allScores->count() > 0 ? round($allScores->avg(), 2) : null,
        ]);
    }

    /**
     * Detailed course report for a specific unit.
     */
    public function courseDetailReport(Request $request, $unit)
    {
        $teacherEvalIds = $this->teacherScopedEvaluationIds();
        $teacherLessonIds = $this->teacherScopedLessonIds();

        $students = EvaluationResult::query()
            ->where('status', 'completed')
            ->when($teacherEvalIds, fn ($q) => $q->whereIn('evaluation_id', $teacherEvalIds))
            ->whereHas('evaluation.lesson', fn ($q) => $q->where('unit', $unit))
            ->select('user_id')
            ->groupBy('user_id')
            ->pluck('user_id')
            ->merge(
                SubmittedWork::where('status', 'graded')
                    ->when($teacherLessonIds, fn ($q) => $q->whereIn('lesson_id', $teacherLessonIds))
                    ->whereHas('lesson', fn ($q) => $q->where('unit', $unit))
                    ->pluck('student_id')
            )
            ->unique();

        // Agregaciones agrupadas en SQL (evita N+1: antes 5 queries/estudiante)
        $studentIds = $students->all();

        $users = User::whereIn('id', $studentIds)
            ->get(['id', 'full_name'])
            ->keyBy('id');

        $evalRows = EvaluationResult::where('status', 'completed')
            ->when($teacherEvalIds, fn ($q) => $q->whereIn('evaluation_id', $teacherEvalIds))
            ->whereHas('evaluation.lesson', fn ($q) => $q->where('unit', $unit))
            ->whereIn('user_id', $studentIds)
            ->selectRaw('user_id, AVG(score) as avg_score, COUNT(*) as total')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $workRows = SubmittedWork::where('status', 'graded')
            ->when($teacherLessonIds, fn ($q) => $q->whereIn('lesson_id', $teacherLessonIds))
            ->whereHas('lesson', fn ($q) => $q->where('unit', $unit))
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, AVG(score) as avg_score, COUNT(*) as total')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $studentData = collect($studentIds)->map(function ($studentId) use ($users, $evalRows, $workRows) {
            $eval = $evalRows->get($studentId);
            $work = $workRows->get($studentId);

            $evalAvg = $eval?->avg_score;
            $workAvg = $work?->avg_score;

            $scores = collect(array_filter([$evalAvg, $workAvg]));
            $average = $scores->count() > 0 ? round($scores->avg(), 2) : null;

            return [
                'student_id' => $studentId,
                'student_name' => $users->get($studentId)?->full_name ?? 'Unknown',
                'average' => $average,
                'evaluations_count' => (int) ($eval?->total ?? 0),
                'submitted_works_count' => (int) ($work?->total ?? 0),
            ];
        })->sortByDesc('average')->values();

        $averages = $studentData->pluck('average')->filter();
        $passCount = $averages->where('>=', 12)->count();

        $stats = [
            'total_students' => $studentData->count(),
            'average' => $averages->count() > 0 ? round($averages->avg(), 2) : null,
            'median' => $averages->count() > 0 ? round($averages->median(), 2) : null,
            'pass_rate' => $averages->count() > 0 ? round(($passCount / $averages->count()) * 100, 2) : null,
            'top_5' => $studentData->take(5),
        ];

        return response()->json([
            'unit' => $unit,
            'students' => $studentData,
            'statistics' => $stats,
        ]);
    }

    private function buildPerformanceHTML($data, $summary)
    {
        $typeRows = '';
        foreach ($summary['by_type'] as $type) {
            $typeRows .= '<tr>';
            $typeRows .= '<td>'.htmlspecialchars($type['type'] ?? 'N/A').'</td>';
            $typeRows .= '<td>'.$type['count'].'</td>';
            $typeRows .= '<td>'.number_format($type['average'], 2).'</td>';
            $typeRows .= '</tr>';
        }

        $topRows = '';
        foreach ($summary['top_students'] as $i => $student) {
            $topRows .= '<tr>';
            $topRows .= '<td>'.($i + 1).'</td>';
            $topRows .= '<td>'.htmlspecialchars($student['student_name'] ?? 'N/A').'</td>';
            $topRows .= '<td>'.$student['count'].'</td>';
            $topRows .= '<td>'.number_format($student['average'], 2).'</td>';
            $topRows .= '</tr>';
        }

        $chartImage = $this->renderBarChartImage($summary['by_type'], 'type');

        $detailRows = '';
        foreach ($data as $row) {
            $detailRows .= '<tr>';
            $detailRows .= '<td>'.htmlspecialchars($row->student_name).'</td>';
            $detailRows .= '<td>'.htmlspecialchars($row->evaluation_title).'</td>';
            $detailRows .= '<td>'.htmlspecialchars($row->lesson_title ?? 'N/A').'</td>';
            $detailRows .= '<td>'.htmlspecialchars($row->area ?? 'N/A').'</td>';
            $detailRows .= '<td>'.number_format($row->score, 1).'</td>';
            $detailRows .= '<td><strong>'.GradesPDFExport::gradeLetter($row->score).'</strong></td>';
            $detailRows .= '<td>'.$row->correct_answers.'/'.$row->total_questions.'</td>';
            $detailRows .= '<td>'.$row->completed_at.'</td>';
            $detailRows .= '</tr>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #2563eb; padding-bottom: 15px; }
            .header h1 { color: #2563eb; margin: 0; font-size: 22px; }
            .header p { color: #666; margin: 5px 0 0; font-size: 11px; }
            .summary { margin-bottom: 25px; }
            .summary h2 { color: #2563eb; font-size: 14px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .stats-grid { display: flex; gap: 10px; margin-bottom: 15px; }
            .stat-box { flex: 1; background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px; text-align: center; }
            .stat-box .value { font-size: 18px; font-weight: bold; color: #1e40af; }
            .stat-box .label { font-size: 9px; color: #666; margin-top: 3px; }
            .chart { text-align: center; margin: 15px 0; }
            .chart img { max-width: 100%; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #2563eb; color: #fff; padding: 8px 6px; text-align: left; font-size: 9px; }
            td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
            tr:nth-child(even) { background: #f9fafb; }
            .section-title { color: #2563eb; font-size: 13px; margin: 20px 0 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        </style></head>
        <body>
            <div class="header">
                <h1>Aulamate - Reporte de Rendimiento</h1>
                <p>Generado: '.now()->format('d/m/Y H:i').'</p>
            </div>
            <div class="summary">
                <h2>Resumen General</h2>
                <div class="stats-grid">
                    <div class="stat-box"><div class="value">'.$summary['total_results'].'</div><div class="label">Total Resultados</div></div>
                    <div class="stat-box"><div class="value">'.number_format($summary['average_score'], 1).'</div><div class="label">Promedio General</div></div>
                    <div class="stat-box"><div class="value">'.number_format($summary['max_score'], 1).'</div><div class="label">Puntaje Máximo</div></div>
                    <div class="stat-box"><div class="value">'.number_format($summary['min_score'], 1).'</div><div class="label">Puntaje Mínimo</div></div>
                </div>
            </div>
            <div class="section-title">Promedio por Tipo de Evaluación</div>
            <div class="chart"><img src="'.$chartImage.'" alt="Promedio por tipo de evaluación" /></div>
            <table>
                <thead><tr><th>Tipo</th><th>Cantidad</th><th>Promedio</th></tr></thead>
                <tbody>'.$typeRows.'</tbody>
            </table>
            <div class="section-title">Mejores Estudiantes</div>
            <table>
                <thead><tr><th>#</th><th>Estudiante</th><th>Evaluaciones</th><th>Promedio</th></tr></thead>
                <tbody>'.$topRows.'</tbody>
            </table>
            <div class="section-title">Resultados Detallados</div>
            <table>
                <thead><tr><th>Estudiante</th><th>Evaluación</th><th>Lección</th><th>Área</th><th>Puntaje</th><th>Calificación</th><th>Correctas/Total</th><th>Fecha</th></tr></thead>
                <tbody>'.$detailRows.'</tbody>
            </table>
        </body></html>';
    }

    private function renderBarChartImage($data, $labelKey = 'type')
    {
        $labels = [];
        $values = [];
        foreach ($data as $item) {
            $labels[] = $item[$labelKey] ?? 'N/A';
            $values[] = (float) ($item['average'] ?? 0);
        }

        $width = 720;
        $height = 280;
        $padLeft = 60;
        $padRight = 20;
        $padTop = 30;
        $padBottom = 55;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $blue = imagecolorallocate($img, 37, 99, 235);
        $grid = imagecolorallocate($img, 229, 231, 235);
        $text = imagecolorallocate($img, 60, 60, 60);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $chartW = $width - $padLeft - $padRight;
        $chartH = $height - $padTop - $padBottom;
        $n = count($values);
        $maxVal = max(20, $values ? max($values) : 20);

        for ($i = 0; $i <= 4; $i++) {
            $y = $padTop + (int) ($chartH * $i / 4);
            imageline($img, $padLeft, $y, $width - $padRight, $y, $grid);
            $label = number_format($maxVal - ($maxVal * $i / 4), 0);
            imagestring($img, 2, 8, $y - 5, $label, $text);
        }

        if ($n > 0) {
            $gap = $chartW / $n;
            $barW = $gap * 0.6;
            foreach ($values as $i => $val) {
                $barH = ($val / $maxVal) * $chartH;
                $x1 = $padLeft + (int) ($i * $gap + ($gap - $barW) / 2);
                $y1 = $padTop + (int) ($chartH - $barH);
                imagefilledrectangle($img, $x1, $y1, $x1 + (int) $barW, $padTop + $chartH, $blue);
                imagestring($img, 2, $x1, $y1 - 12, number_format($val, 1), $text);
                $label = mb_substr((string) $labels[$i], 0, 14);
                imagestring($img, 2, $x1, $padTop + $chartH + 10, $label, $text);
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function buildGradesHTML($data, $summary)
    {
        $detailRows = '';
        foreach ($data as $row) {
            $detailRows .= '<tr>';
            $detailRows .= '<td>'.htmlspecialchars($row->student_name).'</td>';
            $detailRows .= '<td>'.htmlspecialchars($row->student_email ?? '').'</td>';
            $detailRows .= '<td>'.htmlspecialchars($row->evaluation_title).'</td>';
            $detailRows .= '<td>'.htmlspecialchars($row->area ?? 'N/A').'</td>';
            $detailRows .= '<td>'.number_format($row->score, 1).'</td>';
            $detailRows .= '<td><strong>'.GradesPDFExport::gradeLetter($row->score).'</strong></td>';
            $detailRows .= '<td>'.$row->correct_answers.'/'.$row->total_questions.'</td>';
            $detailRows .= '<td>'.$row->completed_at.'</td>';
            $detailRows .= '</tr>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #2563eb; padding-bottom: 15px; }
            .header h1 { color: #2563eb; margin: 0; font-size: 22px; }
            .header p { color: #666; margin: 5px 0 0; font-size: 11px; }
            .stats-grid { display: flex; gap: 10px; margin-bottom: 20px; }
            .stat-box { flex: 1; background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px; text-align: center; }
            .stat-box .value { font-size: 18px; font-weight: bold; color: #1e40af; }
            .stat-box .label { font-size: 9px; color: #666; margin-top: 3px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #2563eb; color: #fff; padding: 8px 6px; text-align: left; font-size: 9px; }
            td { padding: 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
            tr:nth-child(even) { background: #f9fafb; }
            .section-title { color: #2563eb; font-size: 13px; margin: 20px 0 8px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        </style></head>
        <body>
            <div class="header">
                <h1>Aulamate - Calificaciones</h1>
                <p>Generado: '.now()->format('d/m/Y H:i').'</p>
            </div>
            <div class="stats-grid">
                <div class="stat-box"><div class="value">'.$summary['total_results'].'</div><div class="label">Total Registros</div></div>
                <div class="stat-box"><div class="value">'.number_format($summary['average_score'], 1).'</div><div class="label">Promedio</div></div>
                <div class="stat-box"><div class="value">'.number_format($summary['max_score'], 1).'</div><div class="label">Máximo</div></div>
                <div class="stat-box"><div class="value">'.number_format($summary['min_score'], 1).'</div><div class="label">Mínimo</div></div>
            </div>
            <div class="section-title">Detalle de Calificaciones</div>
            <table>
                <thead><tr><th>Estudiante</th><th>Email</th><th>Evaluación</th><th>Área</th><th>Puntaje</th><th>Calificación</th><th>Correctas/Total</th><th>Fecha</th></tr></thead>
                <tbody>'.$detailRows.'</tbody>
            </table>
        </body></html>';
    }

    // ========== MÉTODOS PRIVADOS DE AYUDA ==========

    /**
     * Reporte de participación
     */
    public function participationReport(Request $request)
    {
        $thirtyDaysAgo = now()->subDays(30);

        $activitiesByType = ActivityLog::where('created_at', '>=', $thirtyDaysAgo)
            ->select('activity_type', DB::raw('COUNT(*) as total'))
            ->groupBy('activity_type')
            ->get()
            ->pluck('total', 'activity_type');

        $activitiesPerDay = ActivityLog::where('created_at', '>=', $thirtyDaysAgo)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $mostActiveStudents = ActivityLog::where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('user')
            ->get();

        $firstHalf = ActivityLog::whereBetween('created_at', [$thirtyDaysAgo, now()->subDays(15)])->count();
        $secondHalf = ActivityLog::where('created_at', '>=', now()->subDays(15))->count();
        $activityTrend = $secondHalf >= $firstHalf ? 'increasing' : 'decreasing';

        return response()->json([
            'data' => [
                'activities_by_type' => $activitiesByType,
                'activities_per_day' => $activitiesPerDay,
                'most_active_students' => $mostActiveStudents,
                'activity_trend' => $activityTrend,
            ],
        ]);
    }

    /**
     * Construye el query base de calificaciones, aplicando los mismos filtros
     * que gradesReport/exportPDF/exportExcel usan en común (evita duplicar lógica).
     */
    private function teacherScopedEvaluationIds(): array
    {
        if (! Auth::user()->isScopedTeacher()) {
            return [];
        }

        return Evaluation::where('teacher_id', Auth::id())->pluck('id')->all();
    }

    private function teacherScopedLessonIds(): array
    {
        if (! Auth::user()->isScopedTeacher()) {
            return [];
        }

        return Lesson::where('teacher_id', Auth::id())->pluck('id')->all();
    }

    private function buildGradesQuery(Request $request)
    {
        $query = EvaluationResult::with(['user', 'evaluation'])
            ->where('status', 'completed');

        if ($request->filled('evaluation_id')) {
            $query->where('evaluation_id', $request->evaluation_id);
        }

        if ($request->filled('student_id')) {
            $query->where('user_id', $request->student_id);
        }

        if ($request->filled('period') && $request->period !== 'all_time') {
            $query->whereBetween('created_at', $this->getPeriodDateRange($request->period));
        }

        if (Auth::user()->isScopedTeacher()) {
            $evaluationIds = Evaluation::where('teacher_id', Auth::id())->pluck('id');
            $query->whereIn('evaluation_id', $evaluationIds);
        }

        return $query;
    }

    private function getTopPerformers($query)
    {
        return $query->select('user_id', DB::raw('AVG(score) as avg_score'))
            ->groupBy('user_id')
            ->orderBy('avg_score', 'desc')
            ->limit(5)
            ->with('user')
            ->get();
    }

    private function getDifficultyAreas($query)
    {
        return EvaluationResult::whereIn('evaluation_results.id', $query->pluck('id'))
            ->join('evaluations', 'evaluation_results.evaluation_id', '=', 'evaluations.id')
            ->select('evaluations.type', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->groupBy('evaluations.type')
            ->get();
    }

    private function getPeriodDateRange($period)
    {
        $now = now();

        return match ($period) {
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month', 'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'quarter', 'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->subDays(30), $now],
        };
    }

    private function analyzeStrengths($evaluationResults)
    {
        if ($evaluationResults->isEmpty()) {
            return [
                'strengths' => [],
                'weaknesses' => [],
                'recommendations' => [__('report_complete_more_evaluations')],
            ];
        }

        $byType = $evaluationResults->groupBy('evaluation.type');

        $analysis = [];
        foreach ($byType as $type => $results) {
            $avgScore = $results->avg('score');
            $analysis[$type] = [
                'average' => $avgScore,
                'count' => $results->count(),
                'status' => $avgScore >= 15 ? 'strength' : ($avgScore >= 12 ? 'neutral' : 'weakness'),
            ];
        }

        $strengths = [];
        $weaknesses = [];
        $recommendations = [];

        foreach ($analysis as $type => $data) {
            if ($data['status'] === 'strength') {
                $strengths[] = $type;
            } elseif ($data['status'] === 'weakness') {
                $weaknesses[] = $type;
            }
        }

        if (! empty($weaknesses)) {
            $recommendations[] = __('report_strengthen_weak_areas', ['areas' => implode(', ', $weaknesses)]);
        }
        if (! empty($strengths)) {
            $recommendations[] = __('report_maintain_strengths', ['areas' => implode(', ', $strengths)]);
        }
        if (empty($recommendations)) {
            $recommendations[] = __('report_keep_up_good_work');
        }

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recommendations' => $recommendations,
        ];
    }
}
