<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLES = ['admin', 'teacher', 'student', 'parent', 'coordinador', 'director'];

    public function up(): void
    {
        $this->assertCourseDataIsUnused();

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', self::ROLES)->nullable();
        });

        DB::table('users')->update([
            'role' => DB::raw('(SELECT roles.name FROM roles WHERE roles.id = users.role_id)'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
            $table->enum('role', self::ROLES)->nullable(false)->default('student')->change();
        });
        Schema::dropIfExists('roles');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
        Schema::table('academic_events', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });

        Schema::dropIfExists('attendance');
        Schema::dropIfExists('game_submissions');
        Schema::dropIfExists('games');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('courses');
    }

    public function down(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->enum('name', self::ROLES)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('users')->distinct()->pluck('role') as $role) {
            DB::table('roles')->insert([
                'name' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
        });
        DB::table('users')->update([
            'role_id' => DB::raw('(SELECT roles.id FROM roles WHERE roles.name = users.role)'),
        ]);
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
            $table->foreign('role_id')->references('id')->on('roles');
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('salon_id');
            $table->foreign('salon_id')->references('id')->on('salones')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('teacher_id')->nullable();
            $table->foreign('teacher_id')->references('id')->on('users')->nullOnDelete();
            $table->string('code', 20)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('enrolled_by')->nullable();
            $table->foreign('enrolled_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
        });
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->uuid('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('pin')->nullable();
            $table->text('description')->nullable();
            $table->string('platform')->default('quizizz');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('game_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('score')->nullable();
            $table->string('screenshot_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('grade', 5, 2)->nullable();
            $table->string('teacher_feedback')->nullable();
            $table->integer('xp_awarded')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->string('note')->nullable();
            $table->uuid('recorded_by');
            $table->foreign('recorded_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'student_id', 'date']);
            $table->index(['course_id', 'date']);
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->uuid('course_id')->nullable()->after('teacher_id');
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });
        Schema::table('academic_events', function (Blueprint $table) {
            $table->uuid('course_id')->nullable();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    private function assertCourseDataIsUnused(): void
    {
        $checks = [
            ['courses', null],
            ['enrollments', null],
            ['games', null],
            ['game_submissions', null],
            ['attendance', null],
            ['lessons', 'course_id'],
            ['academic_events', 'course_id'],
        ];

        foreach ($checks as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            if ($column !== null && Schema::hasColumn($table, $column)) {
                $query->whereNotNull($column);
            }
            if ($query->exists()) {
                throw new RuntimeException("Cannot remove courses while {$table} contains dependent data.");
            }
        }
    }
};
