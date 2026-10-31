<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content');
            $table->uuid('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users');
            $table->string('unit')->nullable();
            $table->string('topic')->nullable();
            $table->enum('difficulty', ['basic', 'intermediate', 'advanced'])->default('basic');
            $table->json('tags')->nullable();
            $table->integer('estimated_time')->default(45);
            $table->boolean('is_published')->default(false);
            $table->json('resources')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
