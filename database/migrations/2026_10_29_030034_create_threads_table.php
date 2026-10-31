<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['direct', 'forum'])->default('forum');
            $table->string('title')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('author_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignUuid('student_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignUuid('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignUuid('evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_by']);
            $table->index('author_id');
            $table->index('student_id');
            $table->index('lesson_id');
            $table->index('evaluation_id');
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
