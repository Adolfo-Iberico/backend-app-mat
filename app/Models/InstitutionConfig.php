<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Configuración institucional almacenada para su uso en la aplicación. */
class InstitutionConfig extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'institution_name',
        'primary_color',
        'secondary_color',
        'tertiary_color',
        'background_color',
        'surface_color',
        'logo',
        'email_notifications',
        'backup_frequency',
        'last_backup',
        'allow_tab_switches',
    ];

    protected $casts = [
        'email_notifications' => 'array',
        'last_backup' => 'datetime',
        'allow_tab_switches' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('institution_config.canonical_name'));
        static::deleted(fn () => Cache::forget('institution_config.canonical_name'));
    }

    public static function canonicalName(): ?string
    {
        return Cache::remember(
            'institution_config.canonical_name',
            now()->addMinutes(5),
            fn () => static::query()->value('institution_name')
        );
    }
}
