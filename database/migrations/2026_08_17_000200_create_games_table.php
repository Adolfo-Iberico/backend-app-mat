<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('game_submissions');
        Schema::dropIfExists('games');
    }
};
