<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 1 — Integridad (1/5)
 *
 * Elimina registros huérfanos de submitted_works: filas cuyo lesson_id,
 * evaluation_id o exam_id apuntan a registros que no existen en las
 * tablas origen. Se detectaron 65 filas (44 con evaluation_id roto y
 * 21 con lesson_id roto) provenientes de un lote de seeder del
 * 2026-07-25; al tener status='graded' distorsionan rankings y reportes.
 *
 * Respaldo previo (2026-10-25, antes de esta migración):
 *  - Copia completa de la BD: C:\Users\jose\backups\aulamate\
 *  - Solo filas huérfanas:    submitted_works_orfanos_2026-10-25.sql
 *
 * Debe ejecutarse ANTES de add_missing_foreign_keys: las FK nuevas
 * fallarían al validar filas con referencias rotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sintaxis portable MySQL/SQLite (DELETE multi-tabla no existe en SQLite).
        $deleted = DB::delete(<<<'SQL'
            DELETE FROM submitted_works
            WHERE (lesson_id IS NOT NULL AND lesson_id NOT IN (SELECT id FROM lessons))
               OR (evaluation_id IS NOT NULL AND evaluation_id NOT IN (SELECT id FROM evaluations))
               OR (exam_id IS NOT NULL AND exam_id NOT IN (SELECT id FROM exams))
        SQL);

        if ($deleted > 0) {
            logger()->warning('Fase 1: eliminadas filas huerfanas de submitted_works', [
                'total' => $deleted,
                'respaldo' => 'submitted_works_orfanos_2026-10-25.sql',
            ]);
        }
    }

    public function down(): void
    {
        // Cambio de datos irreversible desde el codigo.
        // Restaurar desde submitted_works_orfanos_2026-10-25.sql si es necesario.
    }
};
