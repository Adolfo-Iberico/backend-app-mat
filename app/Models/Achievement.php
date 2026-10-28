<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Logro configurable con traducciones, criterios y recompensa de experiencia.
 *
 * Desde Fase 3 las traducciones viven en dos columnas JSON (name, description)
 * con los idiomas como claves: {"es": "...", "en": "...", "qu": "..."}.
 * Los accessores name_es/name_en/... mantienen la compatibilidad con el
 * código existente (GamificationService, ExamController, ProgressController).
 */
class Achievement extends Model
{
    public const LOCALES = ['es', 'en', 'qu'];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'xp_reward',
        'category',
        'criteria',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'criteria' => 'array',
        'xp_reward' => 'integer',
    ];

    /** Usuarios que han desbloqueado el logro, con fecha de desbloqueo en la tabla pivote. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /** Devuelve el nombre traducido, usando español si falta la traducción solicitada. */
    public function name(string $locale = 'es'): string
    {
        return $this->name[$locale] ?? $this->name['es'] ?? '';
    }

    /** Devuelve la descripción traducida, usando español como respaldo. */
    public function description(string $locale = 'es'): string
    {
        return $this->description[$locale] ?? $this->description['es'] ?? '';
    }

    // ========== ACCESORES DE COMPATIBILIDAD (columnas planas legacy) ==========

    public function getNameEsAttribute(): string
    {
        return $this->name('es');
    }

    public function getNameEnAttribute(): string
    {
        return $this->name('en');
    }

    public function getNameQuAttribute(): ?string
    {
        return $this->name['qu'] ?? null;
    }

    public function getDescriptionEsAttribute(): string
    {
        return $this->description('es');
    }

    public function getDescriptionEnAttribute(): string
    {
        return $this->description('en');
    }

    public function getDescriptionQuAttribute(): ?string
    {
        return $this->description['qu'] ?? null;
    }
}
