<?php

namespace App\Http\Controllers\Api;

use App\Exports\EvaluationResultExport;
use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\InstitutionConfig;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Role;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\GroqAnswerGrader;
use App\Support\EvaluationAnswerFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Gestiona evaluaciones, preguntas, entregas, resultados y estadísticas de rendimiento.
 */
class EvaluationController extends Controller
{
    /**
     * Listar evaluaciones con filtros
     */
    public function index(Request $request)
    {
        $query = Evaluation::with(['teacher', 'lesson']);

        // Filtros
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('difficulty') && $request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->has('lesson_id') && $request->lesson_id) {
            $query->where('lesson_id', $request->lesson_id);
        }

        if ($request->has('search') && $request->search) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', $search)
                    ->orWhere('description', 'LIKE', $search);
            });
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'published') {
                $query->where('is_published', true);
            } elseif ($request->status === 'draft') {
                $query->where('is_published', false);
            }
        }

        // Si es estudiante, solo mostrar evaluaciones publicadas
        if (Auth::user()->isStudent()) {
            $query->where('is_published', true);

            // Aislamiento por grado: solo evaluaciones de lecciones de su grado.
            $studentGrade = $this->studentGradeOf(Auth::user());
            $query->whereHas('lesson', fn ($q) => $q->where('grade', $studentGrade ?? '__sin_grado__'));

            $query->orderByRaw(
                'CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM evaluation_results
                        WHERE evaluation_results.evaluation_id = evaluations.id
                            AND evaluation_results.user_id = ?
                            AND evaluation_results.status = ?
                    ) THEN 1
                    WHEN evaluations.due_date IS NOT NULL AND evaluations.due_date < ? THEN 2
                    ELSE 0
                END',
                [Auth::id(), EvaluationResult::STATUS_COMPLETED, now()]
            );

            // Mostrar solo evaluaciones disponibles (fecha de entrega no pasada)
            if ($request->has('available') && $request->available) {
                $query->where(function ($q) {
                    $q->where('due_date', '>=', now())
                        ->orWhereNull('due_date');
                });
            }
        }

        // Si es docente, mostrar sus evaluaciones
        if (Auth::user()->isTeacher()) {
            $query->where('teacher_id', Auth::id());
        }

        $evaluations = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 15), 50));

        // Agregar información de resultados para estudiantes (una sola query agrupada, evita N+1)
        if (Auth::user()->isStudent()) {
            $myResults = EvaluationResult::where('user_id', Auth::id())
                ->whereIn('evaluation_id', $evaluations->pluck('id'))
                ->get()
                ->keyBy('evaluation_id');

            $evaluations->getCollection()->transform(function ($evaluation) use ($myResults) {
                $evaluation->user_result = $myResults->get($evaluation->id);

                return $evaluation;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $evaluations,
        ]);
    }

    /**
     * Mostrar una evaluación específica
     */
    public function show($id)
    {
        $evaluation = Evaluation::with(['teacher', 'lesson.course.salon', 'lesson.course.teacher', 'questions'])
            ->findOrFail($id);

        // Verificar permisos
        if (Auth::user()->isStudent()) {
            if (! $evaluation->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => __('evaluation_not_available'),
                ], 403);
            }

            // Aislamiento por grado: prohibido ver evaluaciones de otro grado.
            if ($gradeDenied = $this->gradeViolation($evaluation)) {
                return $gradeDenied;
            }

            // Verificar si el estudiante ya la completó
            $result = EvaluationResult::where('user_id', Auth::id())
                ->where('evaluation_id', $id)
                ->first();

            if ($result && $result->status === 'completed') {
                $evaluation->already_completed = true;
                $evaluation->result = $result;
            }
        }

        // Ocultar respuestas correctas a estudiantes
        if (Auth::user()->isStudent()) {
            foreach ($evaluation->questions as $question) {
                $question->makeHidden(['correct_answer', 'correct_answer_display']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $evaluation,
        ]);
    }

    /**
     * Crear una nueva evaluación
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'type' => 'required|in:exam,quiz,homework,practice',
            'difficulty' => 'required|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'due_date' => 'nullable|date|after:now',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'max_attempts' => 'nullable|integer|min:1|max:10',
        ]);

        // Verificar que la lección exista y pertenezca al docente
        if ($request->has('lesson_id')) {
            $lesson = Lesson::find($request->lesson_id);
            if ($lesson && (string) $lesson->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => __('evaluation_no_permission_associate_lesson'),
                ], 403);
            }
        }

        $evaluation = Evaluation::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'teacher_id' => Auth::id(),
            'lesson_id' => $validated['lesson_id'] ?? null,
            'type' => $validated['type'],
            'difficulty' => $validated['difficulty'],
            'time_limit' => $validated['time_limit'] ?? 30,
            'due_date' => $validated['due_date'] ?? null,
            'auto_correct' => $validated['auto_correct'] ?? true,
            'randomize_questions' => $validated['randomize_questions'] ?? false,
            'max_attempts' => $validated['max_attempts'] ?? 1,
            'is_published' => false,
            'total_questions' => 0,
            'total_points' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('evaluation_created'),
            'data' => $evaluation->load('teacher'),
        ], 201);
    }

    /**
     * Actualizar una evaluación
     */
    public function update(Request $request, $id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_edit'),
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'type' => 'sometimes|in:exam,quiz,homework,practice',
            'difficulty' => 'sometimes|in:basic,intermediate,advanced',
            'time_limit' => 'nullable|integer|min:1|max:999',
            'due_date' => 'nullable|date|after:now',
            'auto_correct' => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'is_published' => 'nullable|boolean',
        ]);

        $oldValues = $evaluation->only(array_keys($validated));
        $evaluation->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('evaluation_updated'),
            'data' => $evaluation->load('teacher'),
        ]);
    }

    /**
     * Eliminar una evaluación
     */
    public function destroy($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_delete'),
            ], 403);
        }

        // Verificar si tiene resultados
        if ($evaluation->results()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_cannot_delete_has_results'),
            ], 400);
        }

        $evaluation->delete();

        return response()->json([
            'success' => true,
            'message' => __('evaluation_deleted'),
        ]);
    }

    /**
     * Publicar una evaluación
     */
    public function publish($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_publish'),
            ], 403);
        }

        // Verificar que tenga preguntas
        if ($evaluation->questions()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_must_have_questions_to_publish'),
            ], 400);
        }

        $evaluation->update([
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Notificar a estudiantes sobre evaluación publicada
        $studentIds = User::forRole(Role::STUDENT)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
        if (! empty($studentIds)) {
            NotificationController::createBulkNotifications(
                $studentIds,
                __('notification_evaluation_available_title'),
                __('notification_evaluation_published_body', ['title' => $evaluation->title]),
                'info',
                "/evaluations/{$evaluation->id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('evaluation_published'),
            'data' => $evaluation,
        ]);
    }

    /**
     * Despublicar una evaluación
     */
    public function unpublish($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_unpublish'),
            ], 403);
        }

        $evaluation->update(['is_published' => false]);

        return response()->json([
            'success' => true,
            'message' => __('evaluation_unpublished'),
        ]);
    }

    /**
     * Obtener preguntas de una evaluación
     */
    public function getQuestions($evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        // Verificar permisos
        if (Auth::user()->isStudent() && ! $evaluation->is_published) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_not_available'),
            ], 403);
        }

        // Aislamiento por grado para estudiantes.
        if ($gradeDenied = $this->gradeViolation($evaluation)) {
            return $gradeDenied;
        }

        $questions = $evaluation->questions()
            ->orderBy('order', 'asc')
            ->get();

        // Si es estudiante, ocultar respuestas correctas
        if (Auth::user()->isStudent()) {
            $questions->makeHidden(['correct_answer']);
            $questions->each->makeHidden('correct_answer_display');
        }

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }

    /**
     * Agregar una pregunta a la evaluación
     */
    public function addQuestion(Request $request, $evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_add_questions'),
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:multiple_choice,fill_blank,drag_drop,formula',
            'question_text' => 'required|string',
            'options' => 'nullable|array',
            'options.*.label' => 'required|string',
            'options.*.value' => 'required|string',
            'correct_answer' => 'required|string',
            'explanation' => 'nullable|string',
            'points' => 'nullable|integer|min:1|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        // Validar opciones para preguntas de opción múltiple
        if ($validated['type'] === 'multiple_choice' && empty($validated['options'])) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_multiple_choice_needs_options'),
            ], 400);
        }

        $question = \DB::transaction(function () use ($evaluationId, $validated) {
            $question = Question::create([
                'id' => Str::uuid(),
                'evaluation_id' => $evaluationId,
                'type' => $validated['type'],
                'question_text' => $validated['question_text'],
                'options' => $validated['options'] ?? null,
                'correct_answer' => $validated['correct_answer'],
                'explanation' => $validated['explanation'] ?? null,
                'points' => $validated['points'] ?? 1,
                'order' => $validated['order'] ?? 0,
            ]);

            // Actualizar total de preguntas y puntos
            $this->updateEvaluationTotals($evaluationId);

            return $question;
        });

        return response()->json([
            'success' => true,
            'message' => __('evaluation_question_added'),
            'data' => $question,
        ], 201);
    }

    /**
     * Actualizar una pregunta
     */
    public function updateQuestion(Request $request, $questionId)
    {
        $question = Question::findOrFail($questionId);
        $evaluation = $question->evaluation;

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_edit_question'),
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:multiple_choice,fill_blank,drag_drop,formula',
            'question_text' => 'sometimes|string',
            'options' => 'nullable|array',
            'options.*.label' => 'required|string',
            'options.*.value' => 'required|string',
            'correct_answer' => 'sometimes|string',
            'explanation' => 'nullable|string',
            'points' => 'nullable|integer|min:1|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        \DB::transaction(function () use ($question, $validated, $evaluation) {
            $question->update($validated);

            // Actualizar total de preguntas y puntos
            $this->updateEvaluationTotals($evaluation->id);
        });

        return response()->json([
            'success' => true,
            'message' => __('evaluation_question_updated'),
            'data' => $question,
        ]);
    }

    /**
     * Eliminar una pregunta
     */
    public function deleteQuestion($questionId)
    {
        $question = Question::findOrFail($questionId);
        $evaluation = $question->evaluation;

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_delete_question'),
            ], 403);
        }

        \DB::transaction(function () use ($question, $evaluation) {
            $question->delete();

            // Actualizar total de preguntas y puntos
            $this->updateEvaluationTotals($evaluation->id);
        });

        return response()->json([
            'success' => true,
            'message' => __('evaluation_question_deleted'),
        ]);
    }

    /**
     * Enviar una evaluación (estudiante)
     */
    public function submit(Request $request, $evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        if (! $evaluation->is_published) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_not_available'),
            ], 403);
        }

        // Aislamiento por grado para estudiantes.
        if ($gradeDenied = $this->gradeViolation($evaluation)) {
            return $gradeDenied;
        }

        // Verificar fecha límite
        if ($evaluation->due_date && now() > $evaluation->due_date) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_due_date_passed'),
            ], 403);
        }

        $validated = $request->validate([
            'attempt_id' => 'nullable|uuid',
            'answers' => 'present|array',
            'answers.*.question_id' => 'required|exists:questions,id|distinct',
            'answers.*.answer' => 'required|string',
            'time_taken' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();

        if (isset($validated['attempt_id'])) {
            EvaluationResult::where('id', $validated['attempt_id'])
                ->where('user_id', $user->id)
                ->where('evaluation_id', $evaluationId)
                ->where('status', EvaluationResult::STATUS_PENDING)
                ->firstOrFail();
        }

        // Verificar intentos
        $attemptsCount = EvaluationResult::where('user_id', $user->id)
            ->where('evaluation_id', $evaluationId)
            ->where('status', 'completed')
            ->count();

        if ($attemptsCount >= $evaluation->max_attempts) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_max_attempts_reached'),
            ], 403);
        }

        try {
            $questions = $evaluation->questions()->orderBy('order')->get();
            $validQuestionIds = $questions->modelKeys();
            foreach ($validated['answers'] as $answerData) {
                if (! in_array($answerData['question_id'], $validQuestionIds, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => __('evaluation_invalid_question'),
                    ], 422);
                }
            }
            $totalQuestions = $questions->count();
            $correctAnswers = 0;
            $totalPoints = (float) $questions->sum('points');
            $answerMap = collect($validated['answers'])->keyBy('question_id');
            $aiItems = [];
            $grades = [];
            $aiGradingWarning = false;

            foreach ($questions as $index => $question) {
                $answerData = $answerMap->get($question->id);
                if (! $answerData) {
                    continue;
                }

                $answer = (string) $answerData['answer'];
                $correctAnswer = $this->resolveCorrectAnswer($question);
                if (EvaluationAnswerFormatter::isCorrect($question, $answer)) {
                    $grades[$question->id] = (float) $question->points;
                } elseif (empty($question->options)) {
                    $aiItems[] = [
                        'index' => $index,
                        'question' => $question->question_text,
                        'expected' => $correctAnswer,
                        'answer' => $answer,
                        'points' => (float) $question->points,
                        'options' => $question->options ?? [],
                        'explanation' => $question->explanation,
                    ];
                } else {
                    $grades[$question->id] = 0.0;
                }
            }

            if ($aiItems) {
                try {
                    $aiGrades = app(GroqAnswerGrader::class)->grade($aiItems);
                    foreach ($aiItems as $item) {
                        $question = $questions[$item['index']];
                        $grades[$question->id] = $aiGrades[$item['index']];
                    }
                } catch (\Throwable $exception) {
                    $aiGradingWarning = true;
                    Log::warning('evaluation_ai_grading_unavailable', [
                        'evaluation_id' => $evaluationId,
                        'user_id' => $user->id,
                        'reason' => $exception->getMessage(),
                    ]);
                    foreach ($aiItems as $item) {
                        $grades[$questions[$item['index']]->id] = 0.0;
                    }
                }
            }

            $result = DB::transaction(function () use ($validated, $user, $evaluation, $evaluationId, $attemptsCount, $questions, $answerMap, $grades, $totalQuestions, $totalPoints, &$correctAnswers) {
                $result = isset($validated['attempt_id'])
                    ? EvaluationResult::where('id', $validated['attempt_id'])
                        ->where('user_id', $user->id)
                        ->where('evaluation_id', $evaluationId)
                        ->where('status', EvaluationResult::STATUS_PENDING)
                        ->lockForUpdate()
                        ->firstOrFail()
                    : EvaluationResult::create([
                        'id' => Str::uuid(),
                        'user_id' => $user->id,
                        'evaluation_id' => $evaluationId,
                        'max_score' => 20,
                        'total_questions' => $totalQuestions,
                        'status' => EvaluationResult::STATUS_PENDING,
                        'started_at' => now(),
                        'attempt_number' => $attemptsCount + 1,
                    ]);

                $earnedPoints = 0.0;
                foreach ($questions as $question) {
                    $answerData = $answerMap->get($question->id);
                    if (! $answerData) {
                        continue;
                    }

                    $pointsEarned = min((float) $question->points, max(0, (float) ($grades[$question->id] ?? 0)));
                    $isCorrect = $pointsEarned >= (float) $question->points;
                    $earnedPoints += $pointsEarned;
                    if ($isCorrect) {
                        $correctAnswers++;
                    }

                    StudentAnswer::create([
                        'id' => Str::uuid(),
                        'user_id' => $user->id,
                        'evaluation_result_id' => $result->id,
                        'question_id' => $question->id,
                        'answer' => $answerData['answer'],
                        'is_correct' => $isCorrect,
                        'points_earned' => $pointsEarned,
                    ]);
                }

                $startedAt = $result->started_at ?? now();
                $elapsedSeconds = max(0, now()->timestamp - $startedAt->timestamp);
                if ($evaluation->time_limit) {
                    $elapsedSeconds = min($elapsedSeconds, $evaluation->time_limit * 60);
                }

                $result->update([
                    'score' => $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 20, 2) : 0,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $totalQuestions,
                    'time_taken' => $elapsedSeconds,
                    'status' => EvaluationResult::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                ActivityService::log('evaluation_completed', $evaluation);

                return $result;
            });

            return response()->json([
                'success' => true,
                'message' => __('evaluation_submitted'),
                'data' => [
                    'result' => $result->load('studentAnswers.question'),
                    'score' => $result->score,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $totalQuestions,
                    'grading_warning' => $aiGradingWarning
                        ? 'No se pudo contactar con Groq; las respuestas de texto no exactas recibieron 0 puntos. Revisa la configuración de GROQ_API_KEY.'
                        : null,
                ],
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('evaluation_submit_error'),
            ], 500);
        }
    }

    public function start(Request $request, $evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        if (! $evaluation->is_published || ($evaluation->due_date && now() > $evaluation->due_date)) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_not_available'),
            ], 403);
        }

        // Aislamiento por grado para estudiantes.
        if ($gradeDenied = $this->gradeViolation($evaluation)) {
            return $gradeDenied;
        }

        $user = Auth::user();
        $result = DB::transaction(function () use ($evaluation, $user) {
            $pending = EvaluationResult::where('evaluation_id', $evaluation->id)
                ->where('user_id', $user->id)
                ->where('status', EvaluationResult::STATUS_PENDING)
                ->lockForUpdate()
                ->latest('started_at')
                ->first();

            if ($pending) {
                return $pending;
            }

            $attemptsCount = EvaluationResult::where('evaluation_id', $evaluation->id)
                ->where('user_id', $user->id)
                ->where('status', EvaluationResult::STATUS_COMPLETED)
                ->count();
            if ($attemptsCount >= $evaluation->max_attempts) {
                abort(403, __('evaluation_max_attempts_reached'));
            }

            return EvaluationResult::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'evaluation_id' => $evaluation->id,
                'max_score' => 20,
                'total_questions' => $evaluation->questions()->count(),
                'status' => EvaluationResult::STATUS_PENDING,
                'started_at' => now(),
                'attempt_number' => $attemptsCount + 1,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $result->id,
                'started_at' => $result->started_at,
                'time_limit' => $evaluation->time_limit,
                'allow_tab_switches' => InstitutionConfig::first()?->allow_tab_switches ?? true,
                'tab_switch_count' => $result->tab_switch_count,
            ],
        ], 201);
    }

    public function reportTabSwitch(Request $request, $evaluationId, $attemptId)
    {
        $result = EvaluationResult::where('id', $attemptId)
            ->where('evaluation_id', $evaluationId)
            ->where('user_id', Auth::id())
            ->where('status', EvaluationResult::STATUS_PENDING)
            ->firstOrFail();

        $result->increment('tab_switch_count');

        return response()->json([
            'success' => true,
            'tab_switch_count' => $result->fresh()->tab_switch_count,
        ]);
    }

    /**
     * Obtener resultados de una evaluación
     */
    public function getResults($evaluationId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        // Verificar permisos
        if (Auth::user()->isStudent()) {
            $results = EvaluationResult::where('user_id', Auth::id())
                ->where('evaluation_id', $evaluationId)
                ->with('studentAnswers.question')
                ->get();
        } else {
            // Docente o admin - verificar acceso
            if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => __('evaluation_no_permission_view_results'),
                ], 403);
            }

            $results = EvaluationResult::where('evaluation_id', $evaluationId)
                ->with(['user', 'studentAnswers.question'])
                ->get();

            // Estadísticas
            $stats = [
                'total_submissions' => $results->count(),
                'average_score' => $results->avg('score') ?? 0,
                'max_score' => $results->max('score') ?? 0,
                'min_score' => $results->min('score') ?? 0,
                'passing_rate' => $this->calculatePassingRate($results),
            ];

            return response()->json([
                'success' => true,
                'data' => $results,
                'stats' => $stats,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    public function exportStudentResultPdf($evaluationId, $resultId)
    {
        [$evaluation, $result, $student] = $this->getOwnedCompletedResult($evaluationId, $resultId);

        return Pdf::loadView('evaluations.result-pdf', [
            'evaluation' => $evaluation,
            'result' => $result,
            'student' => $student,
        ])->download("evaluacion-{$evaluationId}-resultado.pdf");
    }

    public function exportStudentResultExcel($evaluationId, $resultId)
    {
        [$evaluation, $result, $student] = $this->getOwnedCompletedResult($evaluationId, $resultId);

        return Excel::download(
            new EvaluationResultExport($evaluation, $result, $student),
            "evaluacion-{$evaluationId}-resultado.xlsx"
        );
    }

    private function getOwnedCompletedResult($evaluationId, $resultId): array
    {
        $evaluation = Evaluation::with(['teacher', 'lesson.course.salon', 'lesson.course.teacher'])
            ->findOrFail($evaluationId);
        $student = Auth::user();
        $result = EvaluationResult::where('id', $resultId)
            ->where('evaluation_id', $evaluationId)
            ->where('user_id', $student->id)
            ->where('status', EvaluationResult::STATUS_COMPLETED)
            ->with('studentAnswers.question')
            ->firstOrFail();

        return [$evaluation, $result, $student];
    }

    /**
     * Obtener resultado de un estudiante específico
     */
    public function getStudentResult($evaluationId, $userId)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);

        if (Auth::user()->isTeacher() && (string) $evaluation->teacher_id !== (string) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_view_result'),
            ], 403);
        }

        if (! Auth::user()->isTeacher() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_view_result'),
            ], 403);
        }

        $result = EvaluationResult::where('evaluation_id', $evaluationId)
            ->where('user_id', $userId)
            ->with('studentAnswers.question')
            ->first();

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_result_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Duplicar una evaluación
     */
    public function duplicate($id)
    {
        $originalEvaluation = Evaluation::findOrFail($id);

        if ((string) $originalEvaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_duplicate'),
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Duplicar evaluación
            $newEvaluation = $originalEvaluation->replicate();
            $newEvaluation->id = Str::uuid();
            $newEvaluation->title = $originalEvaluation->title.__('evaluation_copy_suffix');
            $newEvaluation->is_published = false;
            $newEvaluation->created_at = now();
            $newEvaluation->updated_at = now();
            $newEvaluation->save();

            // Duplicar preguntas
            foreach ($originalEvaluation->questions as $question) {
                $newQuestion = $question->replicate();
                $newQuestion->id = Str::uuid();
                $newQuestion->evaluation_id = $newEvaluation->id;
                $newQuestion->created_at = now();
                $newQuestion->updated_at = now();
                $newQuestion->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('evaluation_duplicated'),
                'data' => $newEvaluation->load(['teacher', 'questions']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('evaluation_duplicate_error'),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de una evaluación (para docentes)
     */
    public function getStats($id)
    {
        $evaluation = Evaluation::findOrFail($id);

        if ((string) $evaluation->teacher_id !== (string) Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_no_permission_view_stats'),
            ], 403);
        }

        $results = EvaluationResult::where('evaluation_id', $id)
            ->where('status', 'completed')
            ->get();

        $stats = [
            'total_submissions' => $results->count(),
            'average_score' => $results->avg('score') ?? 0,
            'max_score' => $results->max('score') ?? 0,
            'min_score' => $results->min('score') ?? 0,
            'median_score' => $this->calculateMedian($results->pluck('score')->toArray()),
            'passing_rate' => $this->calculatePassingRate($results),
            'score_distribution' => $this->getScoreDistribution($results),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Evaluación adaptativa según rendimiento del estudiante
     */
    public function adaptive(Request $request)
    {
        $user = Auth::user();

        if (! $user->isStudent()) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_not_available'),
            ], 403);
        }

        $recentResults = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with('evaluation')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $typeScores = [];
        foreach ($recentResults as $result) {
            $type = $result->evaluation->type ?? 'practice';
            if (! isset($typeScores[$type])) {
                $typeScores[$type] = [];
            }
            $typeScores[$type][] = $result->score;
        }

        $avgScore = $recentResults->avg('score') ?? 0;

        if ($avgScore >= 16) {
            $difficulty = 'advanced';
        } elseif ($avgScore >= 12) {
            $difficulty = 'intermediate';
        } else {
            $difficulty = 'basic';
        }

        $evaluation = Evaluation::where('type', 'practice')
            ->where('difficulty', $difficulty)
            ->where('is_published', true)
            ->where(function ($q) {
                $q->where('due_date', '>=', now())
                    ->orWhereNull('due_date');
            })
            ->with(['teacher', 'questions'])
            ->first();

        if (! $evaluation) {
            $evaluation = Evaluation::where('difficulty', $difficulty)
                ->where('is_published', true)
                ->where(function ($q) {
                    $q->where('due_date', '>=', now())
                        ->orWhereNull('due_date');
                })
                ->with(['teacher', 'questions'])
                ->first();
        }

        if (! $evaluation) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_not_available'),
            ], 404);
        }

        if ($user->isStudent()) {
            $evaluation->questions->each(function ($question) {
                $question->makeHidden(['correct_answer', 'correct_answer_display']);
            });

            $result = EvaluationResult::where('user_id', $user->id)
                ->where('evaluation_id', $evaluation->id)
                ->first();

            if ($result && $result->status === 'completed') {
                $evaluation->already_completed = true;
                $evaluation->result = $result;
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('adaptive_evaluation'),
            'data' => [
                'difficulty' => $difficulty,
                'average_score' => round($avgScore, 2),
                'type_scores' => collect($typeScores)->map(function ($scores) {
                    return round(collect($scores)->avg(), 2);
                }),
                'evaluation' => $evaluation,
            ],
        ]);
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Verificar si una respuesta es correcta
     */
    private function resolveCorrectAnswer(Question $question): string
    {
        return EvaluationAnswerFormatter::correctAnswer($question);
    }

    private function checkAnswer($question, $answer)
    {
        return EvaluationAnswerFormatter::isCorrect($question, $answer);
    }

    /**
     * Normalizar respuesta para comparación matemática
     */
    private function normalizeAnswer(string $value): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['verdadero', 'true', 'v'])) {
            return 'true';
        }
        if (in_array($value, ['falso', 'false', 'f'])) {
            return 'false';
        }
        // Remove all whitespace
        $value = preg_replace('/\s+/', '', $value);
        // Normalize common math notation
        $value = str_replace(['×', '÷', '²', '³'], ['*', '/', '^2', '^3'], $value);
        // Remove trailing .0 for whole numbers (e.g., "4.0" -> "4")
        $value = preg_replace('/\.0$/', '', $value);
        // Remove leading zeros (e.g., "04" -> "4") but keep "0"
        $value = preg_replace('/^0+(\d)/', '$1', $value);

        return $value;
    }

    /**
     * Actualizar total de preguntas y puntos de una evaluación
     */
    private function updateEvaluationTotals($evaluationId)
    {
        $evaluation = Evaluation::find($evaluationId);
        if (! $evaluation) {
            return;
        }

        $totalQuestions = $evaluation->questions()->count();
        $totalPoints = $evaluation->questions()->sum('points');

        $evaluation->update([
            'total_questions' => $totalQuestions,
            'total_points' => $totalPoints,
        ]);
    }

    /**
     * Calcular tasa de aprobación
     */
    private function calculatePassingRate($results)
    {
        $total = $results->count();
        if ($total === 0) {
            return 0;
        }

        $passing = $results->filter(function ($result) {
            return $result->score >= 12;
        })->count();

        return round(($passing / $total) * 100, 2);
    }

    /**
     * Calcular mediana
     */
    private function calculateMedian($array)
    {
        sort($array);
        $count = count($array);

        if ($count === 0) {
            return 0;
        }
        if ($count % 2 === 0) {
            return ($array[$count / 2 - 1] + $array[$count / 2]) / 2;
        }

        return $array[floor($count / 2)];
    }

    /**
     * Obtener distribución de puntajes
     */
    private function getScoreDistribution($results)
    {
        $distribution = [
            '0-6' => 0,
            '6-12' => 0,
            '12-15' => 0,
            '15-18' => 0,
            '18-20' => 0,
        ];

        foreach ($results as $result) {
            $score = $result->score;
            if ($score < 6) {
                $distribution['0-6']++;
            } elseif ($score < 12) {
                $distribution['6-12']++;
            } elseif ($score < 15) {
                $distribution['12-15']++;
            } elseif ($score < 18) {
                $distribution['15-18']++;
            } else {
                $distribution['18-20']++;
            }
        }

        return $distribution;
    }

    /**
     * Grado escolar canónico del estudiante (1ro..5to), derivado de su
     * salón o del campo grade del usuario. Null si es indefinido.
     */
    private function studentGradeOf($user): ?string
    {
        $raw = $user->salon?->grade ?: $user->grade;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        if (preg_match('/^([1-5])/', trim($raw), $m)) {
            return ['1' => '1ro', '2' => '2do', '3' => '3ro', '4' => '4to', '5' => '5to'][$m[1]];
        }

        return null;
    }

    /**
     * Aislamiento por grado: devuelve respuesta 403 si un estudiante
     * intenta acceder a una evaluación cuya lección pertenece a otro
     * grado (o no tiene lección). Null si el acceso está permitido.
     */
    private function gradeViolation($evaluation): ?JsonResponse
    {
        if (! Auth::user()->isStudent()) {
            return null;
        }

        $lessonGrade = $evaluation->lesson?->grade;
        if ($lessonGrade === null || $lessonGrade !== $this->studentGradeOf(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => __('evaluation_not_available'),
            ], 403);
        }

        return null;
    }
}
