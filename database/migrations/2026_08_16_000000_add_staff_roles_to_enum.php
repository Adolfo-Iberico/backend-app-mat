<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE roles MODIFY COLUMN name ENUM('admin','teacher','student','parent','coordinador','director') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE roles MODIFY COLUMN name ENUM('admin','teacher','student','parent') NOT NULL");
        }
    }
};
