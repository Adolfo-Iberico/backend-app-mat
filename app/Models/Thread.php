<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'type', // 'direct' o 'forum'
        'title',
        'created_by',
        'teacher_id',
        'author_id',
        'student_id',
        'lesson_id',
        'evaluation_id',
        'status',
        'last_message_at',
    ];

    protected $appends = ['teacher_id'];

    public function setTeacherIdAttribute($value): void
    {
        $this->attributes['created_by'] = $value;
    }

    public function getTeacherIdAttribute(): ?string
    {
        return $this->attributes['created_by'] ?? null;
    }

    // ========== SCOPES ==========
    public function scopeDirect($query)
    {
        return $query->where('type', 'direct');
    }

    public function scopeForum($query)
    {
        return $query->where('type', 'forum');
    }

    public function scopeForUser($query, User $u)
    {
        return $query->where(function ($q) use ($u) {
            $q->where('created_by', $u->id)
                ->orWhere('student_id', $u->id);
        });
    }

    // ========== RELACIONES ==========
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'thread_id')->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
