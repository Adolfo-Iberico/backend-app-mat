<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubmittedWork;
use App\Models\LessonProgress;
use App\Models\EvaluationResult;
use App\Models\Evaluation;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestiona entregas asociadas a lecciones y evaluaciones (incluidos exámenes,
 * type='exam'), su revisión docente, calificación por lotes, estadísticas y export.
 *
 * Desde la consolidación del módulo, los trabajos de origen examen referencian
 * evaluation_id con work_type='exam'.
 */
class SubmittedWorkController extends Controller
{
    /**
     * List submitted works with filters.
     */
    public function index(Request $request)
    {
        $query = SubmittedWork::with(['student', 'lesson', 'evaluation']);

        $user = Auth::user();

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isTeacher()) {
            // Evaluaciones (incluye exámenes) y lecciones del docente.
            $teacherEvaluationIds = Evaluation::where('teacher_id', $user->id)->pluck('id');
            $teacherLessonIds = \App\Models\Lesson::where('teacher_id', $user->id)->pluck('id');

            $query->where(function ($q) use ($teacherEvaluationIds, $teacherLessonIds) {
                $q->whereIn('evaluation_id', $teacherEvaluationIds)
                  ->orWhereIn('lesson_id', $teacherLessonIds);
            });
        } elseif (in_array($user->role?->name, [Role::ADMIN, Role::COORDINATOR, Role::DIRECTOR])) {
            // El staff directivo ve todos los trabajos.
        } else {
            abort(403);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }
        if ($request->filled('evaluation_id')) {
            $query->where('evaluation_id', $request->evaluation_id);
        }
        // Compatibilidad: el parámetro exam_id del flujo antiguo mapea a evaluation_id.
        if ($request->filled('exam_id')) {
            $query->where('evaluation_id', $request->exam_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('work_type')) {
            $query->where('work_type', $request->work_type);
        }
        if ($request->filled('student')) {
            $needle = '%' . $request->student . '%';
            $query->whereHas('student', fn ($sq) => $sq
                ->where('full_name', 'LIKE', $needle)
                ->orWhere('email', 'LIKE', $needle));
        }
        if ($request->filled('teacher')) {
            $needle = '%' . $request->teacher . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereHas('evaluation.teacher', fn ($tq) => $tq->where('full_name', 'LIKE', $needle))
                  ->orWhereHas('lesson.teacher', fn ($tq) => $tq->where('full_name', 'LIKE', $needle));
            });
        }
        if ($request->filled('course')) {
            // Búsqueda por área/unidad (collation unicode_ci: 'algebra' encuentra 'Álgebra').
            $needle = '%' . $request->course . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereHas('lesson', fn ($lq) => $lq->where('unit', 'LIKE', $needle))
                  ->orWhereHas('evaluation', fn ($eq) => $eq
                      ->where('unit', 'LIKE', $needle)
                      ->orWhereHas('lesson', fn ($lq) => $lq->where('unit', 'LIKE', $needle)));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $works = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 20), 50));

        return response()->json([
            'data' => $works->items(),
            'meta' => [
                'current_page' => $works->currentPage(),
                'total' => $works->total(),
            ],
        ]);
    }

    /**
     * Store a new submitted work.
     */
    public function store(Request $request)
    {
        $request->validate([
            'lesson_id' => 'nullable|exists:lessons,id',
            'evaluation_id' => 'nullable|exists:evaluations,id',
            // Compatibilidad: el flujo antiguo enviaba exam_id (ahora evaluación type='exam').
            'exam_id' => 'nullable|exists:evaluations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $user = Auth::user();

        $targetEvaluationId = $request->evaluation_id ?? $request->exam_id;
        $targetLessonId = $request->lesson_id;

        if (!$targetLessonId && !$targetEvaluationId) {
            return response()->json(['message' => 'At least one of lesson_id, evaluation_id, or exam_id is required.'], 422);
        }

        // work_type='exam' distingue los trabajos cuyo origen es un examen (type='exam').
        $workType = 'lesson';
        if ($targetEvaluationId) {
            $workType = $request->exam_id ? 'exam' : 'evaluation';
        }

        $score = null;
        $status = 'pending';

        if ($targetEvaluationId) {
            $result = EvaluationResult::where('user_id', $user->id)
                ->where('evaluation_id', $targetEvaluationId)
                ->where('status', 'completed')
                ->first();
            if ($result) {
                $score = (int) $result->score;
                $status = 'submitted';
            }
        } elseif ($targetLessonId) {
            $progress = LessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $targetLessonId)
                ->first();
            if ($progress && $progress->status === 'completed') {
                $status = 'submitted';
            }
        }

        $work = SubmittedWork::create([
            'student_id' => $user->id,
            'lesson_id' => $targetLessonId,
            'evaluation_id' => $targetEvaluationId,
            'work_type' => $workType,
            'title' => $request->title,
            'description' => $request->description,
            'status' => $status,
            'score' => $score,
            'max_score' => 20,
            'attachments' => $request->attachments,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);

        return response()->json(['data' => $work->load(['student', 'lesson', 'evaluation'])], 201);
    }

    /**
     * Show a single submitted work.
     */
    public function show($id)
    {
        $work = SubmittedWork::with(['student', 'lesson', 'evaluation'])->findOrFail($id);

        $user = Auth::user();
        if ($user->isStudent() && $work->student_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $work]);
    }

    /**
     * Teacher grades a submitted work.
     */
    public function grade(Request $request, $id)
    {
        $request->validate([
            'score' => 'required|integer|min:0|max:20',
            'feedback' => 'nullable|string',
        ]);

        $work = SubmittedWork::findOrFail($id);

        $user = Auth::user();
        if (!$this->canGrade($user, $work)) {
            return response()->json(['message' => __('work_grade_forbidden')], 403);
        }

        $work->update([
            'score' => $request->score,
            'teacher_feedback' => $request->feedback ?? $work->teacher_feedback,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        return response()->json(['data' => $work->fresh()->load(['student', 'lesson', 'evaluation'])]);
    }

    /**
     * Califica un lote de trabajos con la misma nota y feedback.
     */
    public function batchGrade(Request $request)
    {
        $validated = $request->validate([
            'work_ids' => 'required|array|min:1',
            'work_ids.*' => 'uuid',
            'score' => 'required|integer|min:0|max:20',
            'feedback' => 'nullable|string',
        ]);

        $user = Auth::user();

        $graded = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $user, &$graded, &$skipped) {
            $works = SubmittedWork::whereIn('id', $validated['work_ids'])->lockForUpdate()->get();

            foreach ($works as $work) {
                if (!$this->canGrade($user, $work)) {
                    $skipped++;
                    continue;
                }

                $work->update([
                    'score' => $validated['score'],
                    'teacher_feedback' => $validated['feedback'] ?? $work->teacher_feedback,
                    'status' => 'graded',
                    'graded_at' => now(),
                ]);
                $graded++;
            }
        });

        return response()->json([
            'message' => "Graded {$graded} works, skipped {$skipped}.",
            'graded' => $graded,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Teacher returns work with feedback.
     */
    public function returnWork(Request $request, $id)
    {
        $request->validate([
            'feedback' => 'required|string',
        ]);

        $work = SubmittedWork::findOrFail($id);

        $user = Auth::user();
        if (!$this->canGrade($user, $work)) {
            return response()->json(['message' => __('work_grade_forbidden')], 403);
        }

        $work->update([
            'teacher_feedback' => $request->feedback,
            'status' => 'returned',
        ]);

        return response()->json(['data' => $work->fresh()->load(['student', 'lesson', 'evaluation'])]);
    }

    /**
     * Verifica que el usuario pueda calificar el trabajo: staff directivo
     * (admin/coordinador/director) o docente propietario del contenido.
     */
    private function canGrade($user, $work)
    {
        if (in_array($user->role?->name, [Role::ADMIN, Role::COORDINATOR, Role::DIRECTOR])) {
            return true;
        }
        if (!$user->isTeacher()) {
            return false;
        }

        // Los trabajos de examen (work_type='exam') referencian evaluation_id.
        if ($work->evaluation_id) {
            return Evaluation::where('id', $work->evaluation_id)
                ->where('teacher_id', $user->id)->exists();
        }
        if ($work->lesson_id) {
            return \App\Models\Lesson::where('id', $work->lesson_id)
                ->where('teacher_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Student's work summary.
     */
    public function studentSummary(Request $request)
    {
        $user = Auth::user();
        if ($user->isTeacher()) {
            $studentId = $request->student_id ?? $user->id;
        } else {
            $studentId = $user->isStudent() ? $user->id : ($request->student_id ?? $user->id);
        }

        $totalWorks = SubmittedWork::where('student_id', $studentId)->count();
        $submitted = SubmittedWork::where('student_id', $studentId)->where('status', 'submitted')->count();
        $graded = SubmittedWork::where('student_id', $studentId)->where('status', 'graded')->count();
        $pending = SubmittedWork::where('student_id', $studentId)->where('status', 'pending')->count();
        $averageGrade = SubmittedWork::where('student_id', $studentId)->where('status', 'graded')->avg('score');

        $submittedPct = $totalWorks > 0 ? round(($submitted / $totalWorks) * 100, 2) : 0;

        $areas = ['Álgebra', 'Geometría', 'Trigonometría'];
        $byArea = [];

        foreach ($areas as $area) {
            $works = SubmittedWork::where('student_id', $studentId)
                ->where('status', 'graded')
                ->whereHas('lesson', fn ($q) => $q->where('unit', $area))
                ->orWhere(function ($q) use ($studentId, $area) {
                    $q->where('student_id', $studentId)
                      ->where('status', 'graded')
                      ->whereHas('evaluation', fn ($eq) => $eq->where('lesson_id', function ($lq) use ($area) {
                          $lq->select('id')->from('lessons')->where('unit', $area);
                      }));
                })
                ->get();

            $byArea[] = [
                'area' => $area,
                'total_works' => $works->count(),
                'average_score' => $works->count() > 0 ? round($works->avg('score'), 2) : null,
            ];
        }

        return response()->json([
            'data' => [
                'student_id' => $studentId,
                'total_works' => $totalWorks,
                'submitted' => $submitted,
                'graded' => $graded,
                'pending' => $pending,
                'percentage_submitted' => $submittedPct,
                'average_grade' => $averageGrade ? round($averageGrade, 2) : null,
                'by_area' => $byArea,
            ],
        ]);
    }

    /**
     * Estadísticas agregadas de trabajos para el panel de administración/dirección.
     */
    public function stats(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role?->name, [Role::ADMIN, Role::COORDINATOR, Role::DIRECTOR])) {
            return response()->json(['message' => __('work_grade_forbidden')], 403);
        }

        $query = SubmittedWork::query();
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $total = (clone $query)->count();
        $submitted = (clone $query)->where('status', 'submitted')->count();
        $graded = (clone $query)->where('status', 'graded')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $averageScore = (clone $query)->where('status', 'graded')->avg('score');

        return response()->json([
            'data' => [
                'total' => $total,
                'submitted' => $submitted,
                'graded' => $graded,
                'pending' => $pending,
                'average_score' => $averageScore !== null ? round((float) $averageScore, 2) : 0,
            ],
        ]);
    }

    /**
     * Exporta los trabajos filtrados a CSV, XLSX o PDF.
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role?->name, [Role::ADMIN, Role::COORDINATOR, Role::DIRECTOR])) {
            return response()->json(['message' => __('work_grade_forbidden')], 403);
        }

        $validated = $request->validate([
            'format' => 'nullable|in:csv,xlsx,pdf,excel',
            'status' => 'nullable|string',
            'work_type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = SubmittedWork::with(['student', 'lesson', 'evaluation'])
            ->orderBy('created_at', 'desc')
            ->limit(5000);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('work_type')) {
            $query->where('work_type', $request->work_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $works = $query->get();
        $format = strtolower($validated['format'] ?? 'csv');
        $filename = 'trabajos_' . now()->format('Y-m-d');

        if ($format === 'xlsx' || $format === 'excel') {
            return Excel::download(new \App\Exports\SubmittedWorksExport($works), $filename . '.xlsx');
        }

        if ($format === 'pdf') {
            return Pdf::loadView('exports.works-pdf', ['works' => $works])
                ->download($filename . '.pdf');
        }

        // CSV (por defecto)
        $headers = [
            'Estudiante', 'Tipo', 'Titulo', 'Estado', 'Nota', 'Nota maxima',
            'Feedback docente', 'Enviado', 'Calificado',
        ];

        return new StreamedResponse(function () use ($works, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($works as $work) {
                fputcsv($out, [
                    $work->student?->full_name,
                    $work->work_type,
                    $work->title,
                    $work->status,
                    $work->score,
                    $work->max_score,
                    $work->teacher_feedback,
                    $work->submitted_at?->format('Y-m-d H:i'),
                    $work->graded_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }

    /**
     * Auto-generate submitted_works from completed lesson_progress and evaluation_results.
     */
    public function autoGenerateFromCompleted(Request $request)
    {
        $user = Auth::user();

        $teacherLessonIds = \App\Models\Lesson::where('teacher_id', $user->id)->pluck('id')->toArray();
        $teacherEvaluationIds = Evaluation::where('teacher_id', $user->id)->pluck('id')->toArray();

        // Solo los exámenes del docente (type='exam') generan trabajos de tipo exam.
        $teacherExamIds = \App\Models\Exam::where('teacher_id', $user->id)->pluck('id')->toArray();

        // Preload titles (evita N+1 de find por fila)
        $lessons = \App\Models\Lesson::whereIn('id', $teacherLessonIds)->pluck('title', 'id');
        $evaluations = Evaluation::whereIn('id', $teacherEvaluationIds)->pluck('title', 'id');
        $exams = \App\Models\Exam::whereIn('id', $teacherExamIds)->pluck('title', 'id');

        // Claves existentes en una sola query por tipo (evita exists() por fila)
        $existingLessonKeys = SubmittedWork::where('work_type', 'lesson')
            ->whereIn('lesson_id', $teacherLessonIds)
            ->get(['student_id', 'lesson_id'])
            ->mapWithKeys(fn ($w) => [$w->student_id . '|' . $w->lesson_id => true]);

        $existingEvalKeys = SubmittedWork::where('work_type', 'evaluation')
            ->whereIn('evaluation_id', $teacherEvaluationIds)
            ->get(['student_id', 'evaluation_id'])
            ->mapWithKeys(fn ($w) => [$w->student_id . '|' . $w->evaluation_id => true]);

        $existingExamKeys = SubmittedWork::where('work_type', 'exam')
            ->whereIn('evaluation_id', $teacherExamIds)
            ->get(['student_id', 'evaluation_id'])
            ->mapWithKeys(fn ($w) => [$w->student_id . '|' . $w->evaluation_id => true]);

        $newWorks = [];
        $now = now();

        // From completed lessons
        $completedLessons = LessonProgress::where('status', 'completed')
            ->whereIn('lesson_id', $teacherLessonIds)
            ->get();

        foreach ($completedLessons as $lp) {
            if (isset($existingLessonKeys[$lp->user_id . '|' . $lp->lesson_id])) {
                continue;
            }
            $newWorks[] = [
                'id' => Str::uuid(),
                'student_id' => $lp->user_id,
                'lesson_id' => $lp->lesson_id,
                'evaluation_id' => null,
                'work_type' => 'lesson',
                'title' => $lessons[$lp->lesson_id] ?? 'Lesson Work',
                'status' => 'submitted',
                'score' => null,
                'max_score' => 20,
                'submitted_at' => $lp->completed_at,
                'graded_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // From evaluation results (sin exámenes)
        $evalResults = EvaluationResult::where('status', 'completed')
            ->whereIn('evaluation_id', array_diff($teacherEvaluationIds, $teacherExamIds))
            ->get();

        foreach ($evalResults as $er) {
            if (isset($existingEvalKeys[$er->user_id . '|' . $er->evaluation_id])) {
                continue;
            }
            $scaledScore = $er->max_score > 0 ? (int) round(($er->score / $er->max_score) * 20) : 0;
            $newWorks[] = [
                'id' => Str::uuid(),
                'student_id' => $er->user_id,
                'lesson_id' => null,
                'evaluation_id' => $er->evaluation_id,
                'work_type' => 'evaluation',
                'title' => $evaluations[$er->evaluation_id] ?? 'Evaluation Work',
                'status' => 'graded',
                'score' => min($scaledScore, 20),
                'max_score' => 20,
                'submitted_at' => $er->completed_at,
                'graded_at' => $er->completed_at,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // From completed exam attempts (evaluation_results de exámenes)
        $examResults = EvaluationResult::where('status', 'completed')
            ->whereIn('evaluation_id', $teacherExamIds)
            ->get();

        foreach ($examResults as $er) {
            if (isset($existingExamKeys[$er->user_id . '|' . $er->evaluation_id])) {
                continue;
            }
            $scaledScore = $er->max_score > 0 ? (int) round(($er->score / $er->max_score) * 20) : 0;
            $newWorks[] = [
                'id' => Str::uuid(),
                'student_id' => $er->user_id,
                'lesson_id' => null,
                'evaluation_id' => $er->evaluation_id,
                'work_type' => 'exam',
                'title' => $exams[$er->evaluation_id] ?? 'Exam Work',
                'status' => 'graded',
                'score' => min($scaledScore, 20),
                'max_score' => 20,
                'submitted_at' => $er->completed_at,
                'graded_at' => $er->completed_at,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Inserción masiva atómica (bulk insert en una sola query)
        $created = count($newWorks);
        if ($created > 0) {
            \DB::transaction(fn () => SubmittedWork::insert($newWorks));
        }

        return response()->json([
            'message' => "Generated {$created} submitted work records.",
            'created' => $created,
        ]);
    }
}
