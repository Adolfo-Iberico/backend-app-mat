<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users');
            $table->uuid('lesson_id')->nullable();
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('set null');
            $table->enum('type', ['exam', 'quiz', 'homework', 'practice'])->default('quiz');
            $table->enum('difficulty', ['basic', 'intermediate', 'advanced'])->default('basic');
            $table->integer('time_limit')->default(30);
            $table->timestamp('due_date')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('auto_correct')->default(true);
            $table->boolean('randomize_questions')->default(false);
            $table->integer('max_attempts')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
