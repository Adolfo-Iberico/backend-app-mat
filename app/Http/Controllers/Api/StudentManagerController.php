<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestor de alumnos para docentes, coordinadores, directores y administradores:
 * carga masiva (CSV/Excel) con asignación automática de grado y sección,
 * listado organizado por salón y acciones rápidas (mover, activar/desactivar).
 */
class StudentManagerController extends Controller
{
    /** Roles permitidos para el import (el resto siempre se asigna student). */
    private const GRADOS = ['1' => '1ro', '2' => '2do', '3' => '3ro', '4' => '4to', '5' => '5to'];

    /** Resumen de alumnos activos por salón (grado y sección). */
    public function summary()
    {
        $salones = Salon::withCount(['students as alumnos' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('grade')->orderBy('section')
            ->get();

        $activos = User::forRole(Role::STUDENT)->where('is_active', true)->count();
        $inactivos = User::forRole(Role::STUDENT)->where('is_active', false)->count();

        return response()->json([
            'success' => true,
            'total_activos' => $activos,
            'total_inactivos' => $inactivos,
            'salones' => $salones->map(fn ($s) => [
                'id' => $s->id,
                'grade' => $s->grade,
                'section' => $s->section,
                'alumnos' => $s->alumnos,
            ]),
        ]);
    }

    /** Listado de alumnos con filtros por grado, sección, búsqueda y estado. */
    public function index(Request $request)
    {
        $query = User::forRole(Role::STUDENT)
            ->leftJoin('salones', 'salones.id', '=', 'users.salon_id')
            ->select('users.*', 'salones.grade as salon_grade', 'salones.section as salon_section');

        if ($request->filled('grade')) {
            $query->where('salones.grade', $request->grade);
        }
        if ($request->filled('section')) {
            $query->where('salones.section', strtoupper(trim($request->section)));
        }
        // Por defecto solo alumnos activos; 'inactive' o 'all' cambian el filtro.
        if ($request->input('state') === 'inactive') {
            $query->where('users.is_active', false);
        } elseif ($request->input('state') !== 'all') {
            $query->where('users.is_active', true);
        }
        if ($request->filled('search')) {
            $buscar = '%'.$request->search.'%';
            $query->where(function ($q) use ($buscar) {
                $q->where('users.full_name', 'like', $buscar)
                    ->orWhere('users.email', 'like', $buscar)
                    ->orWhere('users.dni', 'like', $buscar);
            });
        }

        $students = $query->orderBy('salones.grade')->orderBy('salones.section')
            ->orderBy('users.full_name')
            ->paginate(min((int) ($request->per_page ?? 25), 100));

        return response()->json(['success' => true, 'data' => $students]);
    }

    /**
     * Carga masiva de alumnos desde CSV o Excel (.xlsx).
     * Columnas aceptadas (encabezados flexibles): nombre, correo, dni,
     * grado, seccion. Si el correo ya existe y es alumno, se reasigna su
     * salón; para alumnos nuevos se genera contraseña y se devuelve en
     * la respuesta para entregar las credenciales.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:15360',
        ]);

        $rows = $this->leerFilas($request->file('file')->getPathname(), $request->file('file')->getClientOriginalExtension());
        if ($rows === null) {
            return response()->json(['success' => false, 'message' => 'No se pudo leer el archivo. Verifica el formato (CSV o Excel).'], 422);
        }
        if (count($rows) < 2) {
            return response()->json(['success' => false, 'message' => 'El archivo no contiene filas de alumnos.'], 422);
        }

        $header = array_map(fn ($h) => $this->normalizaClave((string) $h), array_shift($rows));
        $idx = [
            'nombre' => array_search('nombre', $header, true),
            'correo' => array_search('correo', $header, true),
            'dni' => array_search('dni', $header, true),
            'grado' => array_search('grado', $header, true),
            'seccion' => array_search('seccion', $header, true),
        ];

        if ($idx['nombre'] === false || $idx['grado'] === false || $idx['seccion'] === false) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan columnas obligatorias. El archivo debe tener: nombre, correo (opcional si envías dni), grado, seccion.',
            ], 422);
        }

        $importados = 0;
        $actualizados = 0;
        $errores = [];
        $credenciales = [];

        foreach (array_values($rows) as $i => $row) {
            $num = $i + 2; // +2: encabezado + base 1
            $nombre = trim((string) ($row[$idx['nombre']] ?? ''));
            $correo = $idx['correo'] !== false ? strtolower(trim((string) ($row[$idx['correo']] ?? ''))) : '';
            $dni = $idx['dni'] !== false ? trim((string) ($row[$idx['dni']] ?? '')) : '';
            $gradoRaw = trim((string) ($row[$idx['grado']] ?? ''));
            $seccionRaw = trim((string) ($row[$idx['seccion']] ?? ''));

            if ($nombre === '' || $gradoRaw === '' || $seccionRaw === '') {
                $errores[] = "Fila {$num}: faltan nombre, grado o sección.";

                continue;
            }

            $grado = $this->normalizaGrado($gradoRaw);
            $seccion = $this->normalizaSeccion($seccionRaw);
            if ($grado === null) {
                $errores[] = "Fila {$num}: grado no reconocido '{$gradoRaw}' (usa 1ro a 5to o 4to/5to).";

                continue;
            }
            if ($seccion === null) {
                $errores[] = "Fila {$num}: sección no reconocida '{$seccionRaw}' (usa una letra: A, B, C...).";

                continue;
            }

            if ($correo === '' && $dni !== '') {
                $correo = 'alumno'.preg_replace('/\D/', '', $dni).'@mathflow.com';
            }
            if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "Fila {$num}: correo inválido o DNI ausente para generarlo.";

                continue;
            }

            $salon = $this->salonOCrear($grado, $seccion);

            $existente = User::where('email', $correo)->first();
            if ($existente) {
                if ($existente->roleName() !== Role::STUDENT) {
                    $errores[] = "Fila {$num}: el correo {$correo} pertenece a una cuenta de otro rol.";

                    continue;
                }
                $existente->update([
                    'salon_id' => $salon->id,
                    'grade' => $grado,
                    'dni' => $dni !== '' ? $dni : $existente->dni,
                    'is_active' => true,
                ]);
                $actualizados++;

                continue;
            }

            $password = Str::password(12);
            User::create([
                'id' => Str::uuid(),
                'full_name' => $nombre,
                'email' => $correo,
                'password' => Hash::make($password),
                'dni' => $dni !== '' ? $dni : null,
                'role' => Role::STUDENT,
                'salon_id' => $salon->id,
                'grade' => $grado,
                'is_active' => true,
                'provider' => 'email',
            ]);
            $credenciales[] = ['nombre' => $nombre, 'correo' => $correo, 'password' => $password];
            $importados++;
        }

        return response()->json([
            'success' => true,
            'message' => "Importación completada: {$importados} nuevos, {$actualizados} reasignados, ".count($errores).' errores.',
            'importados' => $importados,
            'actualizados' => $actualizados,
            'errores' => $errores,
            'credenciales' => $credenciales,
        ]);
    }

    /** Plantilla CSV lista para Excel en español. */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nombre', 'correo', 'dni', 'grado', 'seccion'], ';');
            fputcsv($out, ['Ejemplo Apellido Apellido', 'ejemplo@correo.com', '71234567', '4to', 'A'], ';');
            fclose($out);
        }, 'plantilla_alumnos.csv', ['Content-Type' => 'text/csv;charset=UTF-8']);
    }

    /** Exporta en CSV los alumnos del filtro actual. */
    public function export(Request $request): StreamedResponse
    {
        $query = User::forRole(Role::STUDENT)
            ->leftJoin('salones', 'salones.id', '=', 'users.salon_id')
            ->select('users.*', 'salones.grade as salon_grade', 'salones.section as salon_section');

        if ($request->filled('grade')) {
            $query->where('salones.grade', $request->grade);
        }
        if ($request->filled('section')) {
            $query->where('salones.section', strtoupper(trim($request->section)));
        }
        if ($request->input('state') === 'inactive') {
            $query->where('users.is_active', false);
        } elseif ($request->input('state') !== 'all') {
            $query->where('users.is_active', true);
        }

        $students = $query->orderBy('salones.grade')->orderBy('salones.section')
            ->orderBy('users.full_name')->get();

        return response()->streamDownload(function () use ($students) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nombre', 'correo', 'dni', 'grado', 'seccion', 'estado'], ';');
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->full_name,
                    $s->email,
                    $s->dni ?? '',
                    $s->salon_grade ?? '',
                    $s->salon_section ?? '',
                    $s->is_active ? 'activo' : 'inactivo',
                ], ';');
            }
            fclose($out);
        }, 'alumnos_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv;charset=UTF-8']);
    }

    /** Mueve un alumno de salón (grado + sección) o activa/desactiva su cuenta. */
    public function update(Request $request, $id)
    {
        $student = User::forRole(Role::STUDENT)->findOrFail($id);

        $validated = $request->validate([
            'grade' => 'sometimes|required_with:section|string',
            'section' => 'sometimes|required_with:grade|string|max:3',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['grade'], $validated['section'])) {
            $grado = $this->normalizaGrado($validated['grade']);
            $seccion = $this->normalizaSeccion($validated['section']);
            if ($grado === null || $seccion === null) {
                return response()->json(['success' => false, 'message' => 'Grado o sección no válidos.'], 422);
            }
            $salon = $this->salonOCrear($grado, $seccion);
            $student->update(['salon_id' => $salon->id, 'grade' => $grado]);
        }

        if (isset($validated['is_active'])) {
            $student->update(['is_active' => $validated['is_active']]);
        }

        return response()->json(['success' => true, 'message' => 'Alumno actualizado.', 'data' => $student]);
    }

    // ==================================================
    // AYUDANTES PRIVADOS
    // ==================================================

    /** Lee las filas de un CSV (delimitador automático , o ;) o de un Excel. */
    private function leerFilas(string $ruta, string $ext): ?array
    {
        if (in_array(strtolower($ext), ['xlsx', 'xls'], true)) {
            try {
                $sheet = IOFactory::load($ruta)->getActiveSheet();
                $rows = [];
                foreach ($sheet->getRowIterator() as $fila) {
                    $cells = [];
                    foreach ($fila->getCellIterator() as $celda) {
                        $cells[] = $celda->getValue();
                    }
                    $rows[] = $cells;
                }

                // Descarta filas totalmente vacías
                return array_values(array_filter($rows, fn ($r) => trim(implode('', array_map('strval', $r))) !== ''));
            } catch (\Throwable) {
                return null;
            }
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            return null;
        }
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido); // BOM
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if (! $lineas) {
            return null;
        }

        $delim = (substr_count($lineas[0], ';') > substr_count($lineas[0], ',')) ? ';' : ',';
        $rows = [];
        foreach ($lineas as $linea) {
            $rows[] = str_getcsv($linea, $delim);
        }

        return $rows;
    }

    /** Normaliza encabezados de columna a claves canónicas. */
    private function normalizaClave(string $k): string
    {
        $k = mb_strtolower(trim($k));
        $mapa = [
            'full_name' => 'nombre', 'nombres' => 'nombre', 'nombre completo' => 'nombre', 'apellido' => 'nombre',
            'email' => 'correo', 'e-mail' => 'correo', 'correo electronico' => 'correo', 'correo electrónico' => 'correo',
            'grade' => 'grado', 'grado escolar' => 'grado',
            'section' => 'seccion', 'salon' => 'seccion', 'salón' => 'seccion', 'aula' => 'seccion', 'sección' => 'seccion',
        ];

        return $mapa[$k] ?? $k;
    }

    /** '4to', '4', '5to Secundaria' -> '4to' | '5to'. Null si no cabe 1-5. */
    private function normalizaGrado(string $raw): ?string
    {
        if (! preg_match('/^([1-5])/', trim($raw), $m)) {
            return null;
        }

        return self::GRADOS[$m[1]] ?? null;
    }

    /** 'a', ' A ', 'Sección A' -> 'A'. Null si vacío. */
    private function normalizaSeccion(string $raw): ?string
    {
        if (preg_match('/([A-Za-z])\s*$/', trim($raw), $m) || preg_match('/([A-Za-z])/', trim($raw), $m)) {
            $letra = strtoupper($m[1]);

            return ctype_alpha($letra) ? $letra : null;
        }

        return null;
    }

    /** Busca el salón (grado, sección) o lo crea si no existe. */
    private function salonOCrear(string $grado, string $seccion): Salon
    {
        return Salon::firstOrCreate(
            ['grade' => $grado, 'section' => $seccion],
            ['id' => (string) Str::uuid()]
        );
    }
}
