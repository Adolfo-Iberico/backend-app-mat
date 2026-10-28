<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 — Integridad (2/5)
 *
 * Añade las claves foráneas que faltaban en 9 tablas.
 *
 * Causa raíz: las migraciones originales usaban uuid('x')->constrained(),
 * que sobre ColumnDefinition es un no-op silencioso: crea la columna pero
 * nunca la restricción FOREIGN KEY (solo foreignId()/foreignUuid()
 * devuelven ForeignKeyDefinition con ->constrained()). Quedaron sin
 * integridad referencial: exams, exam_questions, exam_attempts,
 * submitted_works, academic_events, device_tokens, push_subscriptions
 * y user_achievements (lado user_id).
 *
 * Reglas ON DELETE elegidas en coherencia con el resto del esquema:
 *  - exams.teacher_id:            RESTRICT (igual que evaluations.teacher_id)
 *  - exam_questions.exam_id:      CASCADE  (igual que questions.evaluation_id)
 *  - exam_attempts.*:             CASCADE  (igual que evaluation_results.*)
 *  - submitted_works.*:           CASCADE  (tabla materializada derivada de sus origenes)
 *  - academic_events.user_id:     CASCADE  (agenda personal del usuario)
 *  - academic_events.course_id:   CASCADE  (el evento del curso desaparece con el curso)
 *  - device_tokens / push_subscriptions / user_achievements .user_id:
 *                                 CASCADE  (igual que notifications.user_id)
 *
 * Se ejecuta tras cleanup_submitted_works_orphans para no fallar con filas rotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('users');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('submitted_works', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->cascadeOnDelete();
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });

        Schema::table('academic_events', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('user_achievements', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $t) => $t->dropForeign(['teacher_id']));
        Schema::table('exam_questions', fn (Blueprint $t) => $t->dropForeign(['exam_id']));
        Schema::table('exam_attempts', function (Blueprint $t) {
            $t->dropForeign(['exam_id']);
            $t->dropForeign(['student_id']);
        });
        Schema::table('submitted_works', function (Blueprint $t) {
            $t->dropForeign(['student_id']);
            $t->dropForeign(['lesson_id']);
            $t->dropForeign(['evaluation_id']);
            $t->dropForeign(['exam_id']);
        });
        Schema::table('academic_events', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->dropForeign(['course_id']);
        });
        Schema::table('device_tokens', fn (Blueprint $t) => $t->dropForeign(['user_id']));
        Schema::table('push_subscriptions', fn (Blueprint $t) => $t->dropForeign(['user_id']));
        Schema::table('user_achievements', fn (Blueprint $t) => $t->dropForeign(['user_id']));
    }
};
