<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\ActivityLog;
use App\Models\EvaluationResult;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\UserAchievement;

/** Gestiona las recompensas de experiencia, los logros y el resumen de gamificación. */
class GamificationService
{
    public const XP_LESSON_COMPLETED = 25;

    public const XP_EVALUATION_COMPLETED = 40;

    public const XP_PERFECT_SCORE = 20;

    public const XP_STREAK_BONUS = 10;

    public const XP_DAILY_LOGIN = 5;

    /** Devuelve las definiciones canónicas de logros y sus criterios. */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'first_lesson',
                'name' => ['es' => 'Primera lección', 'en' => 'First lesson', 'qu' => 'Shuk yachay'],
                'description' => ['es' => 'Completa tu primera lección.', 'en' => 'Complete your first lesson.', 'qu' => 'Shuk yachayta tukuchiy.'],
                'icon' => '📖',
                'xp_reward' => 25,
                'category' => 'lessons',
                'criteria' => ['completed_lessons' => 1],
            ],
            [
                'slug' => 'lesson_master',
                'name' => ['es' => 'Maestro de lecciones', 'en' => 'Lesson master', 'qu' => 'Yachay kamachik'],
                'description' => ['es' => 'Completa 10 lecciones.', 'en' => 'Complete 10 lessons.', 'qu' => 'Chunka yachayta tukuchiy.'],
                'icon' => '🏆',
                'xp_reward' => 100,
                'category' => 'lessons',
                'criteria' => ['completed_lessons' => 10],
            ],
            [
                'slug' => 'perfect_score',
                'name' => ['es' => 'Puntuación perfecta', 'en' => 'Perfect score', 'qu' => 'Alli tupu'],
                'description' => ['es' => 'Consigue una puntuación perfecta en una evaluación.', 'en' => 'Get a perfect score on an evaluation.', 'qu' => 'Shuk taripaypi alli tupuyta hapiy.'],
                'icon' => '⭐',
                'xp_reward' => 50,
                'category' => 'exams',
                'criteria' => ['perfect_score' => true],
            ],
            [
                'slug' => 'streak_7',
                'name' => ['es' => 'Racha de 7 días', 'en' => '7-day streak', 'qu' => 'Kanchis punchaw racha'],
                'description' => ['es' => 'Estudia 7 días seguidos.', 'en' => 'Study 7 days in a row.', 'qu' => 'Kanchis punchaw yachakuy.'],
                'icon' => '🔥',
                'xp_reward' => 75,
                'category' => 'streak',
                'criteria' => ['streak' => 7],
            ],
            [
                'slug' => 'streak_30',
                'name' => ['es' => 'Racha de 30 días', 'en' => '30-day streak', 'qu' => 'Kimsa chunka punchaw racha'],
                'description' => ['es' => 'Estudia 30 días seguidos.', 'en' => 'Study 30 days in a row.', 'qu' => 'Kimsa chunka punchaw yachakuy.'],
                'icon' => '⚡',
                'xp_reward' => 200,
                'category' => 'streak',
                'criteria' => ['streak' => 30],
            ],
            [
                'slug' => 'math_genius',
                'name' => ['es' => 'Genio matemático', 'en' => 'Math genius', 'qu' => 'Yupay atiq'],
                'description' => ['es' => 'Consigue un promedio de 18 o más.', 'en' => 'Achieve an average of 18 or more.', 'qu' => 'Chunka pusak yallikta hapiy.'],
                'icon' => '🧠',
                'xp_reward' => 250,
                'category' => 'exams',
                'criteria' => ['average' => 18],
            ],
            [
                'slug' => 'reach_level_5',
                'name' => ['es' => 'Nivel 5 alcanzado', 'en' => 'Reached level 5', 'qu' => 'Pichka kaq pacha'],
                'description' => ['es' => 'Alcanza el nivel 5.', 'en' => 'Reach level 5.', 'qu' => 'Pichka kaq patata hapiy.'],
                'icon' => '🥇',
                'xp_reward' => 150,
                'category' => 'level',
                'criteria' => ['level' => 5],
            ],
            [
                'slug' => 'first_login',
                'name' => ['es' => 'Primer paso', 'en' => 'First step', 'qu' => 'Ñawpa puriy'],
                'description' => ['es' => 'Inicia sesión por primera vez.', 'en' => 'Log in for the first time.', 'qu' => 'Ñawpa kutipi haykuy.'],
                'icon' => '👋',
                'xp_reward' => 10,
                'category' => 'general',
                'criteria' => ['first_login' => true],
            ],
        ];
    }

    /** Sincroniza de forma idempotente las definiciones con la tabla de logros. */
    public static function syncDefinitions(): void
    {
        foreach (self::definitions() as $def) {
            Achievement::updateOrCreate(['slug' => $def['slug']], $def);
        }
    }

    /**
     * Añade experiencia al perfil del estudiante y registra el motivo si se indica.
     *
     * @return array{leveled_up: bool, new_level: int|null, total_xp: int, xp: int}
     */
    public function awardXp(User $user, int $amount, string $reason = ''): array
    {
        $profile = $user->studentProfile;
        if (! $profile) {
            return ['leveled_up' => false, 'new_level' => null, 'total_xp' => 0, 'xp' => 0];
        }

        $oldLevel = $profile->level;
        $result = $profile->addXp($amount);
        $leveledUp = $result['new_level'] > $oldLevel;

        // Comprobar logros de nivel
        if ($leveledUp) {
            $this->checkAchievements($user);
        }

        if ($reason) {
            ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'xp_earned',
                'metadata' => ['amount' => $amount, 'reason' => $reason],
            ]);
        }

        return [
            'leveled_up' => $leveledUp,
            'new_level' => $result['new_level'],
            'total_xp' => $result['total_xp'],
            'xp' => $profile->xp,
        ];
    }

    /**
     * Comprueba los criterios del usuario, desbloquea logros cumplidos y otorga sus recompensas.
     *
     * @return Achievement[] Logros desbloqueados durante esta comprobación.
     */
    public function checkAchievements(User $user): array
    {
        $profile = $user->studentProfile;
        if (! $profile) {
            return [];
        }

        $completedLessons = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        $perfectScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('score', '>=', 19.9)
            ->exists();

        $average = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score');

        $unlocked = UserAchievement::where('user_id', $user->id)->pluck('achievement_id')->all();
        $newly = [];

        foreach (Achievement::all() as $achievement) {
            if (in_array($achievement->id, $unlocked)) {
                continue;
            }

            $criteria = $achievement->criteria ?? [];
            $met = false;

            if (isset($criteria['completed_lessons']) && $completedLessons >= $criteria['completed_lessons']) {
                $met = true;
            }
            if (isset($criteria['streak']) && $profile->current_streak >= $criteria['streak']) {
                $met = true;
            }
            if (isset($criteria['level']) && $profile->level >= $criteria['level']) {
                $met = true;
            }
            if (! empty($criteria['perfect_score']) && $perfectScore) {
                $met = true;
            }
            if (isset($criteria['average']) && ($average ?? 0) >= $criteria['average']) {
                $met = true;
            }
            if (! empty($criteria['first_login'])) {
                $met = true;
            }

            if ($met) {
                UserAchievement::create([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'unlocked_at' => now(),
                ]);
                $newly[] = $achievement;
                $this->awardXp($user, $achievement->xp_reward, 'achievement:'.$achievement->slug);
            }
        }

        return $newly;
    }

    /** Prepara el estado de gamificación y los logros traducidos del usuario. */
    public function gamificationSummary(User $user, string $locale = 'es'): array
    {
        $profile = $user->studentProfile;
        if (! $profile) {
            return ['available' => false];
        }

        $achievements = Achievement::with(['users' => function ($q) use ($user) {
            $q->where('users.id', $user->id);
        }])->get()->map(function ($a) use ($locale) {
            $unlocked = $a->users->isNotEmpty();

            return [
                'slug' => $a->slug,
                'name' => $a->name($locale),
                'description' => $a->description($locale),
                'icon' => $a->icon,
                'xp_reward' => $a->xp_reward,
                'category' => $a->category,
                'unlocked' => $unlocked,
                'unlocked_at' => $unlocked ? $a->users->first()->pivot->unlocked_at : null,
            ];
        });

        return [
            'available' => true,
            'level' => $profile->level,
            'total_xp' => $profile->total_xp,
            'rank_points' => $profile->rank_points,
            'level_progress' => $profile->levelProgress(),
            'achievements' => $achievements,
            'unlocked_count' => $achievements->where('unlocked', true)->count(),
            'total_count' => $achievements->count(),
            'badges' => $profile->badges ?? [],
        ];
    }
}
