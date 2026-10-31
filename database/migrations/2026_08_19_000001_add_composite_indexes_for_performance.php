<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique (user_id, lesson_id): garantiza un solo progreso por estudiante/lección
        // y previene filas duplicadas bajo concurrencia en updateLessonProgress.
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->unique(['user_id', 'lesson_id'], 'lesson_progress_user_lesson_unique');
            $table->index(['lesson_id', 'status'], 'lesson_progress_lesson_status_index');
        });

        // Filtros por rango de fecha + estado (performanceReport, period).
        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'evaluation_results_status_created_index');
        });

        // Ordenamiento estable de preguntas por examen.
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->index(['exam_id', 'order'], 'exam_questions_exam_order_index');
        });

        // Búsquedas por exam_id y por (student_id, work_type) en SubmittedWork.
        Schema::table('submitted_works', function (Blueprint $table) {
            $table->index('exam_id', 'submitted_works_exam_id_index');
            $table->index(['student_id', 'work_type'], 'submitted_works_student_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropUnique('lesson_progress_user_lesson_unique');
            $table->dropIndex('lesson_progress_lesson_status_index');
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropIndex('evaluation_results_status_created_index');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropIndex('exam_questions_exam_order_index');
        });

        Schema::table('submitted_works', function (Blueprint $table) {
            $table->dropIndex('submitted_works_exam_id_index');
            $table->dropIndex('submitted_works_student_type_index');
        });
    }
};
