<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — Elimina columnas redundantes sin ningún uso en el código.
 *
 *  - teacher_profiles.students_count: siempre 0 y derivable de los salones
 *    del docente; ningún controlador o servicio la lee ni escribe.
 *  - student_profiles.xp: duplicaba el concepto de XP acumulado que ya vive
 *    en total_xp (el acumulador real de GamificationService); siempre 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('teacher_profiles', 'students_count')) {
            Schema::table('teacher_profiles', function (Blueprint $table) {
                $table->dropColumn('students_count');
            });
        }

        if (Schema::hasColumn('student_profiles', 'xp')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropColumn('xp');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('teacher_profiles', 'students_count')) {
            Schema::table('teacher_profiles', function (Blueprint $table) {
                $table->unsignedInteger('students_count')->default(0);
            });
        }

        if (! Schema::hasColumn('student_profiles', 'xp')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('xp')->default(0)->after('badges');
            });
        }
    }
};
