<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 — Integridad (4/5)
 *
 * attendance es un registro de caracter legal/auditable: el borrado
 * fisico del docente que registro la asistencia NO debe eliminar el
 * historial que dejo registrado. Cambia ON DELETE CASCADE -> RESTRICT
 * en attendance.recorded_by.
 *
 * student_id y course_id se mantienen en CASCADE (decision documentada):
 * la eliminacion fisica de usuarios es un evento raro (users usa
 * SoftDeletes) y si ocurre, el registro de asistencia de ese alumno
 * pierde sentido sin su titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
            $table->foreign('recorded_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
            $table->foreign('recorded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
