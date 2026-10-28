<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Asigna identificadores DNI secuenciales a las cuentas con rol de estudiante.
 */
class DniSeeder extends Seeder
{
    /**
     * Actualiza el DNI de cada estudiante existente comenzando por 70000001.
     */
    public function run(): void
    {
        $students = DB::table('users')
            ->where('role', 'student')
            ->select('users.id')
            ->get();

        $dni = 70000001;

        foreach ($students as $student) {
            DB::table('users')
                ->where('id', $student->id)
                ->update(['dni' => (string) $dni]);
            $dni++;
        }
    }
}
