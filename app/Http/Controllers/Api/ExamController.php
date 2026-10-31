<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvaluationResult;
use App\Models\Exam;
use App\Models\Notification;
use App\Models\Question;
use App\Models\Role;
use App\Services\GamificationService;
use App\Support\EvaluationAnswerFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestiona exámenes (evaluaciones type='exam' con supervisión anti-cheat)
 * e intentos de estudiantes, incluidas calificaciones e incidencias.
 *
 * Desde la consolidación del módulo, el almacenamiento es el canónico:
 * preguntas en `questions`, intentos en `evaluation_results`. La API y los
 * payloads se mantienen compatibles con el frontend /exams/*.
 */
class ExamController extends Controller
{
    /** Lista los exámenes visibles para el rol actual e incluye el intento propio del estudiante. */
    public function index(Request $request)
    {
        $query = Exam::with(['teacher']);

        if (Auth::user()->isTeacher()) {
            // Coordinador y director ven todos; el docente solo los suyos.
            if (! Auth::user()->isCoordinator() && ! Auth::user()->isDirector()) {
                $query->where('teacher_id', Auth::id());
            }
        } elseif (Auth::user()->isStudent()) {
            $query->where('is_published', true);
        } else {
            abort(403, 'No tienes permiso');
        }

        // Compatibilidad: el parámetro is_active del flujo antiguo mapea a is_published.
        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_published', $request->boolean('is_active'));
        }

        if ($request->filled('unit')) {
            $query->where('unit', $request->unit);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', $search)
                    ->orWhere('description', 'LIKE', $search);
            });
        }

        $exams = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 15), 50));

        // Calcular el intento del estudiante con una sola query (evita N+1 y fuga de correct_answer)
        if (Auth::user()->isStudent()) {
            $attempts = EvaluationResult::where('user_id', Auth::id())
                ->whereIn('evaluation_id', $exams->pluck('id'))
                ->get()
                ->keyBy('evaluation_id');

            $exams->getCollection()->transform(function ($exam) use ($attempts) {
                $exam->user_attempt = $attempts->get($exam->id);

                return $exam;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $exams,
        ]);
    }

    /** Crea un examen en borrador con sus preguntas y devuelve el examen creado. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:100',
            'difficulty' => 'nullable|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|in:multiple_choice,true_false,open_ended',
            'questions.*.question_text' => 'required|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'required|string',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'nullable|integer|min:1|max:100',
            'questions.*.order' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();

        try {
            $totalPoints = 0;
            foreach ($validated['questions'] as $q) {
                $totalPoints += $q['points'] ?? 1;
            }

            $exam = Exam::create([
                'id' => Str::uuid(),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'teacher_id' => Auth::id(),
                'unit' => $validated['unit'] ?? null,
                'difficulty' => $validated['difficulty'] ?? 'basic',
                'time_limit' => $validated['time_limit'] ?? 30,
                'max_attempts' => $validated['max_attempts'] ?? 1,
                'auto_correct' => $validated['auto_correct'] ?? true,
                'randomize_questions' => $validated['randomize_questions'] ?? false,
                'is_published' => false,
                'total_questions' => count($validated['questions']),
                'total_points' => $totalPoints,
            ]);

            foreach ($validated['questions'] as $index => $q) {
                Question::create([
                    'id' => Str::uuid(),
                    'evaluation_id' => $exam->id,
                    'type' => $q['type'],
                    'question_text' => $q['question_text'],
                    'options' => $q['options'] ?? null,
                    'correct_answer' => $q['correct_answer'],
                    'explanation' => $q['explanation'] ?? null,
                    'points' => $q['points'] ?? 1,
                    'order' => $q['order'] ?? $index,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Examen creado como borrador',
                'data' => $exam->load(['teacher', 'questions']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el examen',
            ], 500);
        }
    }

    /** Devuelve el examen y sus preguntas, ocultando las respuestas a no gestores. */
    public function show($id)
    {
        $exam = Exam::with(['teacher', 'questions'])->findOrFail($id);

        if (! $this->canManage($exam)) {
            // Estudiantes: gate de visibilidad. Resto de no gestores (p. ej. padres):
            // pueden ver el examen pero nunca las respuestas correctas.
            if (Auth::user()->isStudent() && ! $exam->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => 'Examen no disponible',
                ], 403);
            }

            if (Auth::user()->isStudent()) {
                $exam->user_attempt = EvaluationResult::where('user_id', Auth::id())
                    ->where('evaluation_id', $id)
                    ->first();
            }

            foreach ($exam->questions as $question) {
                $question->makeHidden(['correct_answer', 'correct_answer_display']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $exam,
        ]);
    }

    /** Actualiza un examen y, si se envían preguntas, reemplaza el conjunto completo. */
    public function update(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);

        if (! $this->canManage($exam)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para editar este examen',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:100',
            'difficulty' => 'sometimes|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'questions' => 'sometimes|array|min:1',
            'questions.*.type' => 'required_with:questions|in:multiple_choice,true_false,open_ended',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'required_with:questions|string',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'nullable|integer|min:1|max:100',
            'questions.*.order' => 'nullable|integer|min:0',
            'questions.*.id' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // El alias is_active del payload mapea a is_published.
            if (array_key_exists('is_active', $validated)) {
                $validated['is_published'] = $validated['is_active'];
                unset($validated['is_active']);
            }

            $examFields = collect($validated)->except('questions')->filter()->toArray();
            if (! empty($examFields)) {
                $exam->update($examFields);
            }

            if (isset($validated['questions'])) {
                // Question usa SoftDeletes: borrado físico para permitir reutilizar
                // los mismos IDs de pregunta que conserva el editor del frontend.
                $exam->questions()->forceDelete();

                $totalPoints = 0;
                foreach ($validated['questions'] as $index => $q) {
                    $totalPoints += $q['points'] ?? 1;
                    Question::create([
                        'id' => $q['id'] ?? Str::uuid(),
                        'evaluation_id' => $exam->id,
                        'type' => $q['type'],
                        'question_text' => $q['question_text'],
                        'options' => $q['options'] ?? null,
                        'correct_answer' => $q['correct_answer'],
                        'explanation' => $q['explanation'] ?? null,
                        'points' => $q['points'] ?? 1,
                        'order' => $q['order'] ?? $index,
                    ]);
                }

                $exam->update([
                    'total_questions' => count($validated['questions']),
                    'total_points' => $totalPoints,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Examen actualizado',
                'data' => $exam->load(['teacher', 'questions']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el examen',
            ], 500);
        }
    }

    /** Elimina un examen que no tenga intentos registrados. */
    public function destroy($id)
    {
        $exam = Exam::findOrFail($id);

        if (! $this->canManage($exam)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para eliminar este examen',
            ], 403);
        }

        if ($exam->attempts()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un examen que ya tiene intentos registrados',
            ], 400);
        }

        $exam->delete();

        return response()->json([
            'success' => true,
            'message' => 'Examen eliminado',
        ]);
    }

    /** Publica (activa) un examen después de comprobar que tenga preguntas. */
    public function activate($id)
    {
        $exam = Exam::findOrFail($id);

        if (! $this->canManage($exam)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para activar este examen',
            ], 403);
        }

        if ($exam->questions()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'El examen debe tener al menos una pregunta para activarlo',
            ], 400);
        }

        $exam->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Examen activado y publicado',
            'data' => $exam,
        ]);
    }

    /** Desactiva el examen indicado. */
    public function deactivate($id)
    {
        $exam = Exam::findOrFail($id);

        if (! $this->canManage($exam)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para desactivar este examen',
            ], 403);
        }

        $exam->update([
            'is_published' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Examen desactivado',
            'data' => $exam,
        ]);
    }

    /** Inicia o retoma el intento del estudiante y devuelve sus preguntas. */
    public function startAttempt($id)
    {
        $exam = Exam::findOrFail($id);

        if (! $exam->is_published) {
            return response()->json([
                'success' => false,
                'message' => 'Este examen no está disponible',
            ], 403);
        }

        $studentId = Auth::id();

        // Transacción con lock FOR UPDATE sobre el examen: serializa intentos simultáneos
        // para evitar duplicar intentos en curso o saltarse max_attempts.
        $result = \DB::transaction(function () use ($exam, $studentId, $id) {
            $lockedExam = Exam::whereKey($exam->id)->lockForUpdate()->first();

            $existingAttempt = EvaluationResult::where('evaluation_id', $id)
                ->where('user_id', $studentId)
                ->where('status', EvaluationResult::STATUS_PENDING)
                ->first();

            if ($existingAttempt) {
                return ['attempt' => $existingAttempt, 'resumed' => true];
            }

            $completedAttempts = EvaluationResult::where('evaluation_id', $id)
                ->where('user_id', $studentId)
                ->where('status', EvaluationResult::STATUS_COMPLETED)
                ->count();

            if ($completedAttempts >= $lockedExam->max_attempts) {
                return ['attempt' => null, 'resumed' => false];
            }

            return [
                'attempt' => EvaluationResult::create([
                    'id' => Str::uuid(),
                    'user_id' => $studentId,
                    'evaluation_id' => $lockedExam->id,
                    'max_score' => 20,
                    'total_questions' => $lockedExam->total_questions,
                    'status' => EvaluationResult::STATUS_PENDING,
                    'started_at' => now(),
                    'attempt_number' => $completedAttempts + 1,
                    'total_points' => $lockedExam->total_points,
                ]),
                'resumed' => false,
            ];
        });

        $attempt = $result['attempt'];
        $resumed = $result['resumed'];

        $questions = $exam->questions()->orderBy('order')->get()->map(function ($q) {
            $q->makeHidden(['correct_answer', 'correct_answer_display']);

            return $q;
        });

        if ($exam->randomize_questions) {
            $questions = $questions->shuffle()->values();
        }

        // Caso A: se alcanzó el máximo de intentos
        if (! $attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Ha alcanzado el número máximo de intentos para este examen',
            ], 403);
        }

        // Caso B: se retomó un intento en curso existente
        if ($resumed) {
            return response()->json([
                'success' => true,
                'message' => 'Retomando intento en curso',
                'data' => [
                    'attempt' => $attempt,
                    'questions' => $questions,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Intento iniciado',
            'data' => [
                'attempt' => $attempt,
                'questions' => $questions,
            ],
        ], 201);
    }

    /** Califica y registra las respuestas del estudiante para un intento en curso. */
    public function submitAttempt(Request $request, $attemptId)
    {
        $attempt = EvaluationResult::findOrFail($attemptId);

        if ((string) $attempt->user_id !== (string) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para enviar este intento',
            ], 403);
        }

        if ($attempt->status !== EvaluationResult::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Este intento ya fue enviado o no está en curso',
            ], 400);
        }

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|string|exists:questions,id',
            'answers.*.answer' => 'required|string',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $exam = $attempt->evaluation;
        $questions = $exam->questions()->get()->keyBy('id');

        DB::beginTransaction();

        try {
            $correctCount = 0;
            $earnedPoints = 0;
            $answersResult = [];

            foreach ($validated['answers'] as $answerData) {
                $question = $questions->get($answerData['question_id']);
                if (! $question) {
                    continue;
                }

                // Comparación tolerante: coincidencia normalizada directa
                // (true/false en español, símbolos matemáticos) o resolución de
                // índice<->texto vía el formatter del ecosistema de evaluaciones.
                $isCorrect = $this->normalizeAnswer($answerData['answer']) === $this->normalizeAnswer($question->correct_answer)
                    || EvaluationAnswerFormatter::isCorrect($question, (string) $answerData['answer']);
                $pointsEarned = $isCorrect ? $question->points : 0;

                if ($isCorrect) {
                    $correctCount++;
                    $earnedPoints += $pointsEarned;
                }

                $answersResult[] = [
                    'question_id' => $question->id,
                    'answer' => $answerData['answer'],
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                ];
            }

            $score = $exam->total_points > 0
                ? round(($earnedPoints / $exam->total_points) * 20)
                : 0;

            // Se normaliza el intento para que también alimente reportes y
            // rankings del ecosistema de evaluaciones (correct_answers y
            // total_questions ahora quedan informados como en el otro flujo).
            $attempt->update([
                'status' => EvaluationResult::STATUS_COMPLETED,
                'score' => $score,
                'correct_answers' => $correctCount,
                'total_questions' => $exam->total_questions,
                'answers' => $answersResult,
                'time_taken' => $validated['time_spent'] ?? 0,
                'completed_at' => now(),
            ]);

            DB::commit();

            // Gamificación: XP por examen completado + logros
            $gamification = null;
            try {
                $user = auth()->user();
                if ($user?->isStudent()) {
                    $gamificationService = app(GamificationService::class);
                    $xp = GamificationService::XP_EVALUATION_COMPLETED;
                    if ($score >= 19.9) {
                        $xp += GamificationService::XP_PERFECT_SCORE;
                    }
                    $gamification = $gamificationService->awardXp($user, $xp, 'exam_completed');
                    $gamification['new_achievements'] = array_map(
                        fn ($a) => ['slug' => $a->slug, 'name' => $a->name_es, 'icon' => $a->icon],
                        $gamificationService->checkAchievements($user)
                    );
                }
            } catch (\Exception $e) {
                report($e);
            }

            return response()->json([
                'success' => true,
                'message' => 'Examen enviado correctamente',
                'data' => [
                    'attempt' => $attempt->fresh(),
                    'score' => $score,
                    'correct_answers' => $correctCount,
                    'total_questions' => $exam->total_questions,
                    'earned_points' => $earnedPoints,
                    'total_points' => $exam->total_points,
                    'answers' => $answersResult,
                ],
                'gamification' => $gamification,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el examen',
            ], 500);
        }
    }

    /** Registra una incidencia de integridad y actualiza el estado del intento si corresponde. */
    public function reportCheating(Request $request, $attemptId)
    {
        $attempt = EvaluationResult::findOrFail($attemptId);

        if ((string) $attempt->user_id !== (string) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para realizar esta acción.',
            ], 403);
        }

        $validated = $request->validate([
            'event' => 'required|string|in:tab_switch,window_blur,copy_attempt,paste_attempt,fullscreen_exit,screenshot_attempt',
            'detail' => 'nullable|string',
        ]);

        $cheatLog = $attempt->cheat_log ?? [];
        $cheatLog[] = [
            'event' => $validated['event'],
            'detail' => $validated['detail'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        $newTabCount = $attempt->tab_switch_count + 1;

        $updateData = [
            'cheat_log' => $cheatLog,
            'tab_switch_count' => $newTabCount,
        ];

        if ($newTabCount >= 3 && $attempt->status === EvaluationResult::STATUS_PENDING) {
            $updateData['status'] = EvaluationResult::STATUS_CHEATING_DETECTED;

            Notification::create([
                'id' => Str::uuid(),
                'user_id' => $attempt->evaluation->teacher_id,
                'title' => 'Actividad sospechosa detectada',
                'message' => 'El estudiante #'.substr($attempt->user_id, 0, 8)." ha sido marcado por actividad sospechosa en el examen \"{$attempt->evaluation->title}\" ({$newTabCount} cambios de pestaña).",
                'type' => Notification::TYPE_WARNING,
                'is_read' => false,
                'link' => "/exams/{$attempt->evaluation_id}/attempts/{$attempt->id}",
            ]);
        }

        $attempt->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Evento registrado',
            'data' => [
                'tab_switch_count' => $newTabCount,
                'status' => $attempt->fresh()->status,
            ],
        ]);
    }

    /** Devuelve totales de intentos, distribución de notas, aprobación e incidencias. */
    public function getExamStats($id)
    {
        $exam = Exam::findOrFail($id);

        if (! $this->canManage($exam)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para ver las estadísticas de este examen',
            ], 403);
        }

        $attempts = EvaluationResult::where('evaluation_id', $id)->get();
        $completedAttempts = $attempts->where('status', EvaluationResult::STATUS_COMPLETED);
        $cheatingIncidents = $attempts->where('status', EvaluationResult::STATUS_CHEATING_DETECTED);

        $scores = $completedAttempts->pluck('score')->filter()->values();
        $totalScores = $scores->count();

        $distribution = [
            '0-6' => 0,
            '7-11' => 0,
            '12-14' => 0,
            '15-17' => 0,
            '18-20' => 0,
        ];

        foreach ($scores as $score) {
            if ($score < 7) {
                $distribution['0-6']++;
            } elseif ($score < 12) {
                $distribution['7-11']++;
            } elseif ($score < 15) {
                $distribution['12-14']++;
            } elseif ($score < 18) {
                $distribution['15-17']++;
            } else {
                $distribution['18-20']++;
            }
        }

        $passingCount = $scores->filter(fn ($s) => $s >= 12)->count();

        $stats = [
            'total_attempts' => $attempts->count(),
            'completed_attempts' => $completedAttempts->count(),
            'in_progress_attempts' => $attempts->where('status', EvaluationResult::STATUS_PENDING)->count(),
            'average_score' => $totalScores > 0 ? round($scores->sum() / $totalScores, 2) : 0,
            'max_score' => $totalScores > 0 ? $scores->max() : 0,
            'min_score' => $totalScores > 0 ? $scores->min() : 0,
            'pass_rate' => $totalScores > 0 ? round(($passingCount / $totalScores) * 100, 2) : 0,
            'cheating_incidents' => $cheatingIncidents->count(),
            'total_cheat_events' => $attempts->sum('tab_switch_count'),
            'score_distribution' => $distribution,
            'average_time_spent' => $completedAttempts->count() > 0
                ? round($completedAttempts->avg('time_taken'))
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /** Historial de intentos y calificaciones del estudiante autenticado. */
    public function myAttempts(Request $request)
    {
        $attempts = EvaluationResult::where('user_id', Auth::id())
            ->whereHas('evaluation', fn ($q) => $q->where('type', Exam::TYPE))
            ->with('evaluation:id,title,unit,difficulty,total_questions,total_points')
            ->orderByDesc('created_at')
            ->paginate(min((int) ($request->per_page ?? 15), 50));

        $summary = [
            'total_attempts' => EvaluationResult::where('user_id', Auth::id())
                ->whereHas('evaluation', fn ($q) => $q->where('type', Exam::TYPE))->count(),
            'completed' => EvaluationResult::where('user_id', Auth::id())
                ->whereHas('evaluation', fn ($q) => $q->where('type', Exam::TYPE))
                ->where('status', EvaluationResult::STATUS_COMPLETED)->count(),
            'in_progress' => EvaluationResult::where('user_id', Auth::id())
                ->whereHas('evaluation', fn ($q) => $q->where('type', Exam::TYPE))
                ->where('status', EvaluationResult::STATUS_PENDING)->count(),
            'average_score' => (float) EvaluationResult::where('user_id', Auth::id())
                ->whereHas('evaluation', fn ($q) => $q->where('type', Exam::TYPE))
                ->where('status', EvaluationResult::STATUS_COMPLETED)->avg('score'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $attempts->items(),
            'meta' => [
                'current_page' => $attempts->currentPage(),
                'total' => $attempts->total(),
            ],
        ]);
    }

    /** Comprueba si el usuario actual gestiona el examen: dueño, coordinador o director. */
    private function canManage(Exam $exam): bool
    {
        $user = Auth::user();

        if (in_array($user->roleName(), [Role::COORDINATOR, Role::DIRECTOR], true)) {
            return true;
        }

        return $user->isTeacher() && (string) $exam->teacher_id === (string) $user->id;
    }

    private function normalizeAnswer(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['verdadero', 'true', 'v'])) {
            return 'true';
        }
        if (in_array($value, ['falso', 'false', 'f'])) {
            return 'false';
        }
        $value = preg_replace('/\s+/', '', $value);
        $value = str_replace(['×', '÷', '²', '³'], ['*', '/', '^2', '^3'], $value);
        $value = preg_replace('/\.0$/', '', $value);
        $value = preg_replace('/^0+(\d)/', '$1', $value);

        return $value;
    }
}
