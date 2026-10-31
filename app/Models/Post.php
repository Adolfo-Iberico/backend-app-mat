<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'thread_id',
        'user_id',
        'body',
        'read_at',
    ];

    protected static function booted(): void
    {
        static::created(function ($post) {
            Thread::where('id', $post->thread_id)->update([
                'last_message_at' => $post->created_at,
            ]);
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
