<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Registra eventos de actividad vinculados al usuario autenticado y a un sujeto opcional. */
class ActivityService
{
    /** Crea una entrada de auditoría con tipo, sujeto y metadatos opcionales. */
    public static function log(string $activityType, $subject = null, ?array $metadata = null): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'id' => Str::uuid(),
            'user_id' => $user?->id,
            'activity_type' => $activityType,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'metadata' => $metadata,
        ]);
    }
}
