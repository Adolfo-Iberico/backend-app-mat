<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — Consolidación del módulo de evaluaciones.
 *
 * Unifica el módulo paralelo de exámenes dentro del ecosistema canónico de
 * evaluaciones. Los tres flujos absorben sus datos PRESERVANDO los IDs, por
 * lo que toda referencia externa (submitted_works, conversaciones, foros)
 * sigue siendo válida:
 *
 *   exams         -> evaluations        (type='exam'; unit se conserva;
 *                                        is_active se pliega a is_published)
 *   exam_questions-> questions          (evaluation_id = exam_id; enum de
 *                                        type ampliado con true_false/open_ended)
 *   exam_attempts -> evaluation_results (user_id=student_id,
 *                                        evaluation_id=exam_id,
 *                                        status in_progress->pending;
 *                                        se añaden answers/cheat_log JSON y
 *                                        total_points; attempt_number real:
 *                                        se elimina de paso el bug del
 *                                        UNIQUE(exam_id,student_id) que
 *                                        impedía max_attempts > 1)
 *
 * submitted_works.exam_id se pliega a evaluation_id (work_type='exam' mantiene
 * la distinción de origen). Finalmente se retiran las tres tablas absorbedas.
 *
 * Respaldo previo: C:\Users\jose\backups\aulamate\mentematica_pre_fase2_2026-10-25.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluations') || ! Schema::hasTable('questions')) {
            return;
        }

        // 1. evaluations: unidad temática usada por el flujo de exámenes.
        Schema::table('evaluations', function (Blueprint $table) {
            $table->string('unit', 100)->nullable()->after('lesson_id');
        });

        // 2. questions: enum de tipos ampliado con los del flujo de exámenes.
        $this->widenEnum('questions', 'type', [
            'multiple_choice', 'fill_blank', 'drag_drop', 'formula', 'true_false', 'open_ended',
        ], false);

        // 3. evaluation_results: estado de integridad + columnas del flujo de exámenes.
        $this->widenEnum('evaluation_results', 'status', [
            'pending', 'completed', 'failed', 'cheating_detected',
        ], true, 'pending');

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->unsignedInteger('total_points')->default(0)->after('attempt_number');
            $table->json('answers')->nullable()->after('total_points');
            $table->json('cheat_log')->nullable()->after('answers');
        });

        if (! Schema::hasTable('exams')) {
            return; // Entorno ya consolidado.
        }

        // 4. exams -> evaluations (IDs preservados; is_active plegado a is_published).
        DB::statement(<<<'SQL'
            INSERT INTO evaluations
                (id, title, description, teacher_id, lesson_id, type, difficulty, time_limit,
                 due_date, is_published, auto_correct, randomize_questions, max_attempts,
                 published_at, total_questions, total_points, unit,
                 created_at, updated_at, deleted_at)
            SELECT id, title, description, teacher_id, NULL, 'exam', difficulty,
                   COALESCE(time_limit, 30), NULL,
                   CASE WHEN is_active = 1 OR is_published = 1 THEN 1 ELSE 0 END,
                   auto_correct, randomize_questions, max_attempts,
                   published_at, total_questions, total_points, unit,
                   created_at, updated_at, deleted_at
            FROM exams
        SQL);

        // 5. exam_questions -> questions (evaluation_id = exam_id, IDs preservados).
        //    El flujo antiguo guardaba correct_answer como ÍNDICE de options
        //    ('0','1','2'); el canónico guarda el TEXTO de la opción. Se convierte
        //    en PHP para operar igual en MySQL y SQLite.
        if (Schema::hasTable('exam_questions')) {
            DB::table('exam_questions')->orderBy('created_at')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $options = json_decode((string) $row->options, true) ?? [];
                    $correct = (string) $row->correct_answer;

                    // Índice 0-based -> texto de la opción (string plano o {label,value}).
                    if (ctype_digit($correct) && isset($options[(int) $correct])) {
                        $option = $options[(int) $correct];
                        $correct = is_array($option)
                            ? (string) ($option['value'] ?? $option['label'] ?? $correct)
                            : (string) $option;
                    }

                    DB::table('questions')->insert([
                        'id' => $row->id,
                        'evaluation_id' => $row->exam_id,
                        'type' => $row->type,
                        'question_text' => $row->question_text,
                        'options' => $row->options,
                        'correct_answer' => $correct,
                        'explanation' => $row->explanation,
                        'points' => $row->points,
                        'order' => $row->order,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
        }

        // 6. exam_attempts -> evaluation_results (in_progress -> pending; intentos reales).
        if (Schema::hasTable('exam_attempts')) {
            DB::statement(<<<'SQL'
                INSERT INTO evaluation_results
                    (id, user_id, evaluation_id, score, max_score, correct_answers, total_questions,
                     time_taken, status, started_at, completed_at, attempt_number,
                     tab_switch_count, total_points, answers, cheat_log, created_at, updated_at)
                SELECT a.id, a.student_id, a.exam_id, a.score, 20, 0,
                       COALESCE((SELECT e.total_questions FROM exams e WHERE e.id = a.exam_id), 0),
                       a.time_spent,
                       CASE a.status
                           WHEN 'in_progress' THEN 'pending'
                           WHEN 'cheating_detected' THEN 'cheating_detected'
                           ELSE 'completed'
                       END,
                       a.started_at, a.completed_at,
                       ROW_NUMBER() OVER (PARTITION BY a.student_id, a.exam_id
                                          ORDER BY a.started_at, a.created_at),
                       a.tab_switch_count, a.total_points, a.answers, a.cheat_log,
                       a.created_at, a.updated_at
                FROM exam_attempts a
            SQL);
        }

        // 7. submitted_works: pliegue de exam_id a evaluation_id (work_type='exam' distingue).
        if (Schema::hasColumn('submitted_works', 'exam_id')) {
            DB::statement(<<<'SQL'
                UPDATE submitted_works
                   SET evaluation_id = exam_id
                 WHERE exam_id IS NOT NULL AND evaluation_id IS NULL
            SQL);

            Schema::table('submitted_works', function (Blueprint $table) {
                // SQLite reconstruye la tabla en dropColumn y fallaría al recrear
                // un indice de la columna eliminada: se retira antes, en ambos drivers.
                $table->dropForeign(['exam_id']);
                $table->dropIndex('submitted_works_exam_id_index');
                $table->dropColumn('exam_id');
            });
        }

        // 8. Retirada de las tablas absorbedas.
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams');
    }

    public function down(): void
    {
        // Reversión estructural (sin datos: restaurar desde el respaldo previo).
        // Los enums se dejan ampliados: es una operación segura e idempotente.

        if (! Schema::hasTable('exams')) {
            Schema::create('exams', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->uuid('teacher_id');
                $table->string('unit')->nullable();
                $table->string('difficulty')->default('basic');
                $table->integer('time_limit')->nullable();
                $table->integer('max_attempts')->default(1);
                $table->boolean('auto_correct')->default(true);
                $table->boolean('randomize_questions')->default(false);
                $table->boolean('is_active')->default(false);
                $table->boolean('is_published')->default(false);
                $table->unsignedInteger('total_questions')->default(0);
                $table->unsignedInteger('total_points')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['teacher_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('exam_questions')) {
            Schema::create('exam_questions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('exam_id');
                $table->string('type')->default('multiple_choice');
                $table->text('question_text');
                $table->json('options')->nullable();
                $table->string('correct_answer');
                $table->text('explanation')->nullable();
                $table->integer('points')->default(1);
                $table->integer('order')->default(0);
                $table->timestamps();
                $table->index('exam_id');
                $table->index(['exam_id', 'order']);
            });
        }

        if (! Schema::hasTable('exam_attempts')) {
            Schema::create('exam_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('exam_id');
                $table->uuid('student_id');
                $table->string('status')->default('in_progress');
                $table->integer('score')->nullable();
                $table->integer('total_points')->default(0);
                $table->json('answers')->nullable();
                $table->integer('time_spent')->default(0);
                $table->integer('tab_switch_count')->default(0);
                $table->json('cheat_log')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'student_id']);
                $table->index(['student_id', 'status']);
            });
        }

        if (Schema::hasColumn('submitted_works', 'evaluation_id') && ! Schema::hasColumn('submitted_works', 'exam_id')) {
            Schema::table('submitted_works', function (Blueprint $table) {
                $table->uuid('exam_id')->nullable()->after('evaluation_id');
                $table->index('exam_id');
            });
        }

        if (Schema::hasColumn('evaluation_results', 'cheat_log')) {
            Schema::table('evaluation_results', function (Blueprint $table) {
                $table->dropColumn(['total_points', 'answers', 'cheat_log']);
            });
        }

        if (Schema::hasColumn('evaluations', 'unit')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }

    /**
     * Amplía un ENUM conservando compatibilidad MySQL/MariaDB (ALTER MODIFY)
     * y SQLite (reconstrucción nativa de ->change() en Laravel 11+).
     */
    private function widenEnum(string $table, string $column, array $values, bool $hasDefault, ?string $default = null): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            Schema::table($table, function (Blueprint $t) use ($column, $values, $hasDefault, $default) {
                $definition = $t->enum($column, $values);
                if ($hasDefault) {
                    $definition->default($default);
                }
                $definition->change();
            });

            return;
        }

        $list = "'".implode("','", $values)."'";
        $sql = "ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$list}) NOT NULL"
            .($hasDefault ? " DEFAULT '{$default}'" : '');

        $connection->statement($sql);
    }
};
