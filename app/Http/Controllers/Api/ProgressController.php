<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Evaluation;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\EvaluationResult;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\GamificationService;

/**
 * Proporciona paneles para estudiantes y docentes, progreso de lecciones, insignias y estadísticas.
 */
class ProgressController extends Controller
{
    /**
     * Dashboard del estudiante
     */
    public function studentDashboard()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        if (!$studentProfile) {
            $studentProfile = (object) [
                'average_score' => 0,
                'current_streak' => 0,
                'total_time_spent' => 0,
                'badges' => [],
            ];
        }

        // Lecciones en curso
        $inProgressLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('status', [LessonProgress::STATUS_IN_PROGRESS, LessonProgress::STATUS_NOT_STARTED])
            ->with('lesson')
            ->get();

        // Lecciones completadas recientemente
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->with('lesson')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        // Últimas evaluaciones
        $recentEvaluations = EvaluationResult::where('user_id', $user->id)
            ->with('evaluation')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Estadísticas del estudiante
        $stats = [
            'total_lessons' => Lesson::published()->count(),
            'completed_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('status', LessonProgress::STATUS_COMPLETED)
                ->count(),
            'in_progress_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('status', LessonProgress::STATUS_IN_PROGRESS)
                ->count(),
            'average_score' => $studentProfile->average_score ?? 0,
            'current_streak' => $studentProfile->current_streak ?? 0,
            'total_time_spent' => $studentProfile->total_time_spent ?? 0,
            'badges' => $studentProfile->badges ?? []
        ];

        // Próximas evaluaciones — publicadas, con fecha límite futura, que el estudiante no haya completado
        $completedEvaluationIds = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('evaluation_id');

        $upcomingEvaluations = Evaluation::where('is_published', true)
            ->where(function ($query) {
                $query->where('due_date', '>=', now())
                      ->orWhereNull('due_date');
            })
            ->whereNotIn('id', $completedEvaluationIds)
            ->with('teacher')
            ->orderBy('due_date', 'asc')
            ->limit(3)
            ->get();

        return response()->json([
            'user' => $user,
            'profile' => $studentProfile,
            'stats' => $stats,
            'in_progress_lessons' => $inProgressLessons,
            'completed_lessons' => $completedLessons,
            'recent_evaluations' => $recentEvaluations,
            'upcoming_evaluations' => $upcomingEvaluations
        ]);
    }

    /**
     * Dashboard del docente
     */
    public function teacherDashboard()
    {
        $user = Auth::user();

        // Obtener todas las evaluaciones del docente
        $evaluationIds = Evaluation::where('teacher_id', $user->id)->pluck('id');
        
        // Estadísticas del docente
        $stats = [
            'total_students' => User::where('role', Role::STUDENT)->count(),
            'total_lessons' => Lesson::where('teacher_id', $user->id)->count(),
            'total_evaluations' => Evaluation::where('teacher_id', $user->id)->count(),
            'published_lessons' => Lesson::where('teacher_id', $user->id)
                ->where('is_published', true)
                ->count(),
            'published_evaluations' => Evaluation::where('teacher_id', $user->id)
                ->where('is_published', true)
                ->count(),
            'total_submissions' => EvaluationResult::whereIn('evaluation_id', $evaluationIds)->count(),
            'average_score' => EvaluationResult::whereIn('evaluation_id', $evaluationIds)
                ->where('status', 'completed')
                ->avg('score') ?? 0,
            'pending_reviews' => EvaluationResult::whereIn('evaluation_id', $evaluationIds)
                ->where('status', 'pending')
                ->count()
        ];

        // Últimos estudiantes activos
        $recentStudents = User::where('role', Role::STUDENT)
            ->whereNotNull('last_login')
            ->orderBy('last_login', 'desc')
            ->limit(10)
            ->get();

        // Evaluaciones recientes
        $recentEvaluations = Evaluation::where('teacher_id', $user->id)
            ->with('results')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'user' => $user->load('teacherProfile'),
            'stats' => $stats,
            'recent_students' => $recentStudents,
            'recent_evaluations' => $recentEvaluations
        ]);
    }

    /**
     * Dashboard ligero del estudiante — solo estadísticas (para mobile)
     * Evita descargar colecciones completas en redes lentas
     */
    public function studentStats()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        if (!$studentProfile) {
            $studentProfile = (object) [
                'average_score' => 0,
                'current_streak' => 0,
                'total_time_spent' => 0,
                'badges' => [],
            ];
        }

        $stats = [
            'total_lessons' => Lesson::published()->count(),
            'completed_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('status', LessonProgress::STATUS_COMPLETED)
                ->count(),
            'in_progress_lessons' => LessonProgress::where('user_id', $user->id)
                ->where('status', LessonProgress::STATUS_IN_PROGRESS)
                ->count(),
            'average_score' => $studentProfile->average_score ?? 0,
            'current_streak' => $studentProfile->current_streak ?? 0,
            'total_time_spent' => $studentProfile->total_time_spent ?? 0,
            'badges_count' => count($studentProfile->badges ?? []),
            'pending_evaluations' => EvaluationResult::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count(),
            'completed_evaluations' => EvaluationResult::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count(),
        ];

        return response()->json([
            'user' => $user,
            'stats' => $stats
        ]);
    }

    /**
     * Obtener progreso de una lección específica
     */
    public function getLessonProgress($lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);
        
        $progress = LessonProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lessonId)
            ->first();

        if (!$progress) {
            // Crear progreso inicial
            $progress = LessonProgress::create([
                'id' => Str::uuid(),
                'user_id' => Auth::id(),
                'lesson_id' => $lessonId,
                'progress' => 0,
                'status' => LessonProgress::STATUS_NOT_STARTED,
                'time_spent' => 0,
                'last_position' => 0
            ]);
        }

        return response()->json([
            'lesson' => $lesson,
            'progress' => $progress
        ]);
    }

    /**
     * Actualizar progreso de una lección
     */
    public function updateLessonProgress(Request $request, $lessonId)
    {
        // Solo estudiantes avanzan en lecciones
        if (!Auth::user()->isStudent()) {
            return response()->json(['message' => 'Solo los estudiantes pueden actualizar el progreso'], 403);
        }

        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'time_spent' => 'nullable|integer|min:0',
            'last_position' => 'nullable|integer|min:0'
        ]);

        $lesson = Lesson::findOrFail($lessonId);

        $progress = \DB::transaction(function () use ($lessonId, $validated) {
            $progress = LessonProgress::where('user_id', Auth::id())
                ->where('lesson_id', $lessonId)
                ->lockForUpdate()
                ->first();

            if (!$progress) {
                $progress = LessonProgress::create([
                    'id' => Str::uuid(),
                    'user_id' => Auth::id(),
                    'lesson_id' => $lessonId,
                    'progress' => 0,
                    'status' => LessonProgress::STATUS_NOT_STARTED,
                    'time_spent' => 0,
                    'last_position' => 0
                ]);
            }

            $updateData = [
                'progress' => $validated['progress']
            ];

            if (isset($validated['time_spent'])) {
                $updateData['time_spent'] = ($progress->time_spent ?? 0) + $validated['time_spent'];
            }

            if (isset($validated['last_position'])) {
                $updateData['last_position'] = $validated['last_position'];
            }

            // Actualizar estado según el progreso
            if ($validated['progress'] >= 100) {
                $updateData['status'] = LessonProgress::STATUS_COMPLETED;
                $updateData['completed_at'] = now();

                // Actualizar perfil del estudiante
                $this->updateStudentProfileOnCompletion();
            } elseif ($validated['progress'] > 0) {
                $updateData['status'] = LessonProgress::STATUS_IN_PROGRESS;
            }

            $progress->update($updateData);

            return $progress;
        });

        if ($progress->status === LessonProgress::STATUS_COMPLETED) {
            \App\Services\ActivityService::log('lesson_completed', $lesson);
        }

        // Verificar y otorgar insignias
        $badges = $this->checkAndAwardBadges();

        // Gamificación: XP por lección completada + logros
        $gamification = null;
        if ($progress->status === LessonProgress::STATUS_COMPLETED) {
            $gamificationService = app(GamificationService::class);
            $gamification = $gamificationService->awardXp(Auth::user(), GamificationService::XP_LESSON_COMPLETED, 'lesson_completed');
            $gamification['new_achievements'] = array_map(
                fn ($a) => ['slug' => $a->slug, 'name' => $a->name_es, 'icon' => $a->icon],
                $gamificationService->checkAchievements(Auth::user())
            );
        }

        return response()->json([
            'message' => __('progress_updated'),
            'progress' => $progress,
            'badges' => $badges,
            'gamification' => $gamification
        ]);
    }

    /**
     * Obtener estadísticas de progreso del usuario
     */
    public function getMyStats()
    {
        $user = Auth::user();
        
        // Progreso general
        $totalLessons = Lesson::published()->count();
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        // Tiempo total
        $totalTime = LessonProgress::where('user_id', $user->id)
            ->sum('time_spent');

        // Evaluaciones 
        $totalEvaluations = Evaluation::published()->count();
        $completedEvaluations = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        // Rendimiento
        $averageScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score') ?? 0;

        // Distribución de progreso por lección
        $progressDistribution = LessonProgress::where('user_id', $user->id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Actividad reciente (últimos 7 días)
        $recentActivity = $this->getRecentActivity($user->id);

        return response()->json([
            'summary' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'completion_rate' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0,
                'total_time_spent' => $totalTime,
                'total_evaluations' => $totalEvaluations,
                'completed_evaluations' => $completedEvaluations,
                'average_score' => round($averageScore, 2)
            ],
            'progress_distribution' => $progressDistribution,
            'recent_activity' => $recentActivity
        ]);
    }

    /**
     * Historial completo de lecciones y evaluaciones del estudiante autenticado.
     */
    public function myLearningRecord()
    {
        $user = Auth::user();
        $lessonProgress = LessonProgress::where('user_id', $user->id)
            ->with('lesson:id,title,unit,grade')
            ->orderByDesc('updated_at')
            ->get();
        $evaluationResults = EvaluationResult::where('user_id', $user->id)
            ->with('evaluation:id,title,lesson_id')
            ->whereIn('status', ['completed', 'pending'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'lesson_progress' => $lessonProgress,
            'evaluation_results' => $evaluationResults,
            'summary' => [
                'completed_lessons' => $lessonProgress->where('status', LessonProgress::STATUS_COMPLETED)->count(),
                'in_progress_lessons' => $lessonProgress->where('status', LessonProgress::STATUS_IN_PROGRESS)->count(),
                'completed_evaluations' => $evaluationResults->where('status', 'completed')->count(),
                'average_score' => round((float) $evaluationResults
                    ->where('status', 'completed')
                    ->avg('score'), 2),
            ],
        ]);
    }

    /**
     * Obtener insignias del usuario
     */
    public function getBadges()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        // Solo estudiantes tienen insignias
        if (!$studentProfile) {
            return response()->json([
                'badges' => [],
                'unlocked_count' => 0,
                'total_badges' => 0
            ]);
        }

        $badges = $studentProfile->badges ?? [];

        // Definir todas las insignias disponibles
        $availableBadges = [
            [
                'id' => 'first_lesson',
                'name' => __('badge_first_lesson_name'),
                'description' => __('badge_first_lesson_desc'),
                'icon' => '🎓'
            ],
            [
                'id' => 'lesson_master',
                'name' => __('badge_lesson_master_name'),
                'description' => __('badge_lesson_master_desc'),
                'icon' => '📚'
            ],
            [
                'id' => 'perfect_score',
                'name' => __('badge_perfect_score_name'),
                'description' => __('badge_perfect_score_desc'),
                'icon' => '⭐'
            ],
            [
                'id' => 'streak_7',
                'name' => __('badge_streak_7_name'),
                'description' => __('badge_streak_7_desc'),
                'icon' => '🔥'
            ],
            [
                'id' => 'streak_30',
                'name' => __('badge_streak_30_name'),
                'description' => __('badge_streak_30_desc'),
                'icon' => '💎'
            ],
            [
                'id' => 'math_genius',
                'name' => __('badge_math_genius_name'),
                'description' => __('badge_math_genius_desc'),
                'icon' => '🧠'
            ]
        ];

        // Marcar insignias obtenidas
        foreach ($availableBadges as &$badge) {
            $badge['unlocked'] = in_array($badge['id'], $badges);
        }

        return response()->json([
            'badges' => $availableBadges,
            'unlocked_count' => count($badges),
            'total_badges' => count($availableBadges)
        ]);
    }

    /**
     * Nivel del estudiante según promedio de evaluaciones
     */
    public function studentLevel()
    {
        $user = Auth::user();

        $averageScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score') ?? 0;

        $averageScore = round($averageScore, 2);

        if ($averageScore >= 16) {
            $level = 'advanced';
            $nextLevel = null;
            $pointsToNextLevel = 0;
        } elseif ($averageScore >= 12) {
            $level = 'intermediate';
            $nextLevel = 'advanced';
            $pointsToNextLevel = round(16 - $averageScore, 2);
        } else {
            $level = 'beginner';
            $nextLevel = 'intermediate';
            $pointsToNextLevel = round(12 - $averageScore, 2);
        }

        return response()->json([
            'success' => true,
            'message' => __('student_level'),
            'data' => [
                'level' => $level,
                'level_label' => __($level),
                'average_score' => $averageScore,
                'next_level' => $nextLevel,
                'next_level_label' => $nextLevel ? __($nextLevel . '_level') : null,
                'points_to_next_level' => max(0, $pointsToNextLevel)
            ]
        ]);
    }

    // ========== REPORTE PROPIO (estudiante) ==========

    /** Descarga el reporte de progreso del estudiante autenticado en PDF. */
    public function exportMyReportPdf()
    {
        $service = app(\App\Services\StudentReportService::class);

        return $service->pdf(Auth::id(), 'mi-reporte.pdf');
    }

    /** Descarga el reporte de progreso del estudiante autenticado en Excel. */
    public function exportMyReportExcel()
    {
        $service = app(\App\Services\StudentReportService::class);

        return $service->excel(Auth::id(), 'mi-reporte.xlsx');
    }

    // ========== PERFIL ACADÉMICO PROPIO (estudiante) ==========

    /** Actualiza el grado y el nivel académico del estudiante autenticado. */
    public function updateMyStudentProfile(Request $request)
    {
        $user = Auth::user();

        if (!$user->isStudent()) {
            return response()->json(['message' => __('student_profile_forbidden')], 403);
        }

        $validated = $request->validate([
            'grade' => 'sometimes|string|max:50',
            'academic_level' => 'sometimes|in:basic,intermediate,advanced',
        ]);

        if (array_key_exists('grade', $validated)) {
            $user->update(['grade' => $validated['grade']]);
        }

        if (array_key_exists('academic_level', $validated)) {
            $profile = $user->studentProfile;
            if (!$profile) {
                $profile = \App\Models\StudentProfile::create([
                    'user_id' => $user->id,
                    'academic_level' => $validated['academic_level'],
                ]);
            } else {
                $profile->update(['academic_level' => $validated['academic_level']]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('student_profile_updated'),
            'data' => $user->fresh()->load('studentProfile'),
        ]);
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Actualizar perfil del estudiante al completar una lección
     */
    private function updateStudentProfileOnCompletion()
    {
        $studentProfile = Auth::user()->studentProfile;
        if ($studentProfile) {
            // Streak logic: track consecutive days of activity
            $today = now()->startOfDay();
            $lastActivity = $studentProfile->last_activity_date
                ? \Carbon\Carbon::parse($studentProfile->last_activity_date)->startOfDay()
                : null;

            if ($lastActivity) {
                $daysSinceLastActivity = $today->diffInDays($lastActivity);

                if ($daysSinceLastActivity === 0) {
                    // Already active today, don't change streak
                } elseif ($daysSinceLastActivity === 1) {
                    // Active yesterday, increment streak
                    $studentProfile->increment('current_streak');
                } else {
                    // Missed days, reset streak
                    $studentProfile->current_streak = 1;
                }
            } else {
                // First activity ever
                $studentProfile->current_streak = 1;
            }

            $studentProfile->last_activity_date = $today;
            $studentProfile->save();
        }
    }

    /**
     * Verificar y otorgar insignias
     */
    private function checkAndAwardBadges()
    {
        $user = Auth::user();
        $studentProfile = $user->studentProfile;

        // Solo estudiantes tienen perfil y pueden ganar insignias/rachas
        if (!$studentProfile) {
            return [];
        }

        $badges = $user->achievements()->pluck('achievements.slug')->all();
        $newBadges = [];
        $awardBadge = function (string $badgeId) use ($user, &$badges, &$newBadges): void {
            if (in_array($badgeId, $badges, true)) {
                return;
            }

            $definition = collect(GamificationService::definitions())->firstWhere('slug', $badgeId);
            if ($definition === null) {
                throw new \LogicException("No achievement definition exists for [$badgeId].");
            }

            $achievement = Achievement::firstOrCreate(['slug' => $badgeId], $definition);
            $user->achievements()->syncWithoutDetaching([
                $achievement->id => ['unlocked_at' => now()],
            ]);
            $badges[] = $badgeId;
            $newBadges[] = $badgeId;
        };

        // Verificar insignias
        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        // Primera lección
        if ($completedLessons >= 1) {
            $awardBadge('first_lesson');
        }

        // Maestro de lecciones
        if ($completedLessons >= 10) {
            $awardBadge('lesson_master');
        }

        // Racha de 7 días
        if ($studentProfile->current_streak >= 7) {
            $awardBadge('streak_7');
        }

        // Racha de 30 días
        if ($studentProfile->current_streak >= 30) {
            $awardBadge('streak_30');
        }

        // Puntuación perfecta
        $perfectScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('score', '>=', 19.9)
            ->exists();

        if ($perfectScore) {
            $awardBadge('perfect_score');
        }

        // Genio matemático
        $averageScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score');

        if ($averageScore >= 18) {
            $awardBadge('math_genius');
        }

        // Crear notificaciones para nuevas insignias
        foreach ($newBadges as $badgeId) {
            $this->createBadgeNotification($user->id, $badgeId);
        }

        return $newBadges;
    }

    /**
     * Crear notificación de insignia
     */
    private function createBadgeNotification($userId, $badgeId)
    {
        $badgeNames = [
            'first_lesson' => __('notification_first_lesson_title'),
            'lesson_master' => __('notification_lesson_master_title'),
            'perfect_score' => __('notification_perfect_score_title'),
            'streak_7' => __('notification_streak_7_title'),
            'streak_30' => __('notification_streak_30_title'),
            'math_genius' => __('notification_math_genius_title'),
        ];

        NotificationController::createNotification(
            $userId, 
            __('notification_new_badge_unlocked_title'),
            $badgeNames[$badgeId] ?? __('notification_new_badge_unlocked_body'),
            'success'
        );
    }

    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity($userId)
    {
        $activity = [];

        // Lecciones completadas recientemente
        $recentLessons = LessonProgress::where('user_id', $userId)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->with('lesson')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentLessons as $lesson) {
            $activity[] = [
                'type' => 'lesson_completed',
                'title' => __('activity_lesson_completed') . $lesson->lesson->title,
                'date' => $lesson->completed_at,
                'icon' => '📖'
            ];
        }

        // Evaluaciones completadas recientemente
        $recentEvaluations = EvaluationResult::where('user_id', $userId)
            ->where('status', 'completed')
            ->with('evaluation')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentEvaluations as $evaluation) {
            $activity[] = [
                'type' => 'evaluation_completed',
                'title' => __('activity_evaluation_completed') . $evaluation->evaluation->title . ' (Puntuación: ' . $evaluation->score . ')',
                'date' => $evaluation->completed_at,
                'icon' => '📝'
            ];
        }

        // Ordenar por fecha
        usort($activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activity, 0, 10);
    }
}