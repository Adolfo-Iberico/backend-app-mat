<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The v2 schema keeps roles, courses, and their foreign-key relationships.
    }

    public function down(): void
    {
        // This migration intentionally makes no schema changes.
    }
};
