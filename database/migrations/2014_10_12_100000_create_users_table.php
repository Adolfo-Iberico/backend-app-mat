<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('full_name');
            $table->foreignId('role_id')->constrained('roles');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('institution')->nullable();
            $table->string('grade')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('google_token')->nullable();
            $table->string('provider')->default('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
