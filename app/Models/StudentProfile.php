<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Perfil académico y de gamificación de un usuario estudiante. */
class StudentProfile extends Model
{
    use HasUuids;

    protected $table = 'user_profiles';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'academic_level',
        'current_streak',
        'last_activity_date',
        'total_xp',
        'xp',
        'level',
        'rank_points',
        'department',
        'specialization',
        'years_experience',
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'last_activity_date' => 'date',
        'xp' => 'integer',
        'level' => 'integer',
        'rank_points' => 'integer',
    ];

    protected $appends = [
        'total_lessons_completed',
        'average_score',
        'total_time_spent',
        'badges',
        'xp',
    ];

    public function getTotalLessonsCompletedAttribute(): int
    {
        return $this->lessonProgress()
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();
    }

    public function getAverageScoreAttribute(): float
    {
        return round((float) ($this->evaluationResults()
            ->where('status', EvaluationResult::STATUS_COMPLETED)
            ->avg('score') ?? 0), 2);
    }

    public function getTotalTimeSpentAttribute(): int
    {
        return (int) $this->lessonProgress()->sum('time_spent');
    }

    public function getBadgesAttribute(): array
    {
        return UserAchievement::query()
            ->join('achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $this->user_id)
            ->pluck('achievements.slug')
            ->all();
    }

    public function getXpAttribute(): int
    {
        return (int) ($this->attributes['xp'] ?? 0);
    }

    public function getTotalXpAttribute(): int
    {
        return $this->getXpAttribute();
    }

    public function setTotalXpAttribute(int $value): void
    {
        $this->attributes['xp'] = $value;
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'user_id', 'user_id');
    }

    public function evaluationResults(): HasMany
    {
        return $this->hasMany(EvaluationResult::class, 'user_id', 'user_id');
    }

    /**
     * XP acumulado necesario para alcanzar un nivel (progresión triangular: 100/200/300...).
     */
    public static function cumulativeXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        return 100 * ($level - 1) * $level / 2;
    }

    /**
     * Nivel derivado del total_xp.
     */
    public static function levelFromXp(int $totalXp): int
    {
        $level = 1;
        while (self::cumulativeXpForLevel($level + 1) <= $totalXp) {
            $level++;
        }

        return $level;
    }

    /**
     * Progreso (0-100) hacia el siguiente nivel basado en total_xp.
     */
    public function levelProgress(): array
    {
        $level = self::levelFromXp($this->total_xp);
        $base = self::cumulativeXpForLevel($level);
        $next = self::cumulativeXpForLevel($level + 1);
        $span = max(1, $next - $base);
        $progress = (int) round((($this->total_xp - $base) / $span) * 100);

        return [
            'level' => $level,
            'total_xp' => $this->total_xp,
            'xp_in_level' => $this->total_xp - $base,
            'xp_for_current_level' => $base,
            'xp_for_next_level' => $next,
            'progress_percent' => max(0, min(100, $progress)),
            'xp_to_next_level' => max(0, $next - $this->total_xp),
        ];
    }

    /**
     * Añade XP, actualiza total_xp/nivel y devuelve el resultado.
     * Atómico: bloquea la fila con FOR UPDATE para evitar pérdidas de XP bajo concurrencia.
     */
    public function addXp(int $amount): array
    {
        \DB::transaction(function () use ($amount) {
            $locked = static::whereKey($this->user_id)->lockForUpdate()->first();
            $locked->total_xp += $amount;
            $locked->level = self::levelFromXp($locked->total_xp);
            $locked->save();
            $this->setRawAttributes($locked->getAttributes());
        });

        return ['new_level' => $this->level, 'total_xp' => $this->total_xp];
    }

    // ========== RELACIONES ==========
    /** Usuario al que corresponde el perfil. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
