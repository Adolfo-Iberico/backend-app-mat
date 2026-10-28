<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/** Evento académico personal o institucional. */
class AcademicEvent extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'type',
        'color',
        'all_day',
        'is_public',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'all_day' => 'boolean',
        'is_public' => 'boolean',
    ];

    /** Usuario propietario del evento. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Filtra los eventos del usuario y los marcados como públicos. */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('is_public', true);
        });
    }

    /** Selecciona eventos cuyo inicio está dentro del rango indicado. */
    public function scopeInRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('start_date', [$start, $end]);
    }
}
