<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token', 500);
            $table->string('p256dh')->nullable();
            $table->string('auth')->nullable();
            $table->enum('platform', ['android', 'ios', 'web']);
            $table->string('device_name')->nullable();
            $table->string('device_model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('platform');
            $table->index('is_active');
            $table->unique('token', 'uq_device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
