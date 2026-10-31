<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perfil unificado de usuario que almacena datos adicionales tanto para estudiantes como para docentes.
 */
class UserProfile extends Model
{
    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'address',
        'bio',
        'avatar',
        'grade',
        'salon',
        'academic_level',
        'xp',
        'level',
        'rank_points',
        'current_streak',
        'last_activity_date',
        'department',
        'specialization',
        'years_experience',
    ];

    protected $casts = [
        'xp' => 'integer',
        'level' => 'integer',
        'rank_points' => 'integer',
        'current_streak' => 'integer',
        'last_activity_date' => 'date',
        'years_experience' => 'integer',
    ];

    /**
     * El usuario al que pertenece este perfil.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
