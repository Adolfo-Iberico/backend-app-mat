<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Registro de una actividad del usuario y de su entidad asociada, si existe. */
class ActivityLog extends Model
{
    use HasUuids;

    protected $table = 'activity_log';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'activity_type',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /** Usuario que originó la actividad. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
