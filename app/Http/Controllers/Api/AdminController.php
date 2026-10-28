<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\AcademicPeriod;
use App\Models\InstitutionConfig;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Gestiona usuarios, configuración institucional, períodos académicos y copias de seguridad.
 */
class AdminController extends Controller
{
    // ========== GESTIÓN DE USUARIOS ==========
    
    /** Devuelve usuarios paginados, opcionalmente filtrados por rol o búsqueda. */
    public function getUsers(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            if (in_array($request->role, [Role::ADMIN, Role::TEACHER, Role::STUDENT, Role::DIRECTOR, Role::COORDINATOR], true)) {
                $query->where('role', $request->role);
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('email', 'LIKE', '%' . $search . '%');
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 20), 1000));

        return response()->json($users);
    }

    /** Devuelve un usuario con su rol y perfiles asociados. */
    public function getUser($id)
    {
        $user = User::with(['studentProfile', 'teacherProfile'])
            ->findOrFail($id);

        return response()->json($user);
    }

    /** Crea un usuario y el perfil asociado a su rol; devuelve el usuario creado. */
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:teacher,student,director,coordinador',
            'grade' => 'nullable|string'
        ]);

        $user = User::create([
            'id' => Str::uuid(),
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'grade' => $validated['grade'] ?? null,
            'is_active' => true,
            'provider' => 'email'
        ]);

        if ($validated['role'] === Role::STUDENT) {
            StudentProfile::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'academic_level' => 'basic'
            ]);
        } elseif (in_array($validated['role'], [Role::TEACHER, Role::COORDINATOR, Role::DIRECTOR], true)) {
            TeacherProfile::create([
                'id' => Str::uuid(),
                'user_id' => $user->id
            ]);
        }

        return response()->json([
            'message' => __('user_created'),
            'user' => $user
        ], 201);
    }

    /** Actualiza los campos editables de una cuenta y devuelve el usuario actualizado. */
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|in:teacher,student,director,coordinador',
            'grade' => 'nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        if (isset($validated['role'])) {
            $validated['role'] = strtolower($validated['role']);
        }

        $oldValues = $user->only(array_keys($validated));
        $user->update($validated);

        return response()->json([
            'message' => __('user_updated'),
            'user' => $user
        ]);
    }

    /** Elimina un usuario salvo que lo impidan las protecciones de cuentas administradoras. */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->isAdmin()) {
            return response()->json([
                'message' => __('cannot_delete_admin_user')
            ], 403);
        }

        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => __('cannot_delete_own_account')
            ], 403);
        }

        $activeAdminCount = User::where('role', Role::ADMIN)
            ->where('is_active', true)
            ->count();

        if ($activeAdminCount <= 1) {
            return response()->json([
                'message' => __('cannot_delete_last_active_admin')
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => __('user_deleted')
        ]);
    }

    /** Activa la cuenta indicada. */
    public function activateUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json([
            'message' => __('user_activated')
        ]);
    }

    /** Desactiva la cuenta indicada y revoca sus tokens de acceso. */
    public function deactivateUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->isAdmin()) {
            if ($user->id === Auth::id()) {
                return response()->json([
                    'message' => __('cannot_deactivate_own_account')
                ], 403);
            }

            $activeAdminCount = User::where('role', Role::ADMIN)
                ->where('is_active', true)
                ->count();

            if ($activeAdminCount <= 1) {
                return response()->json([
                    'message' => __('cannot_deactivate_last_active_admin')
                ], 403);
            }
        }

        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => __('cannot_deactivate_own_account')
            ], 403);
        }

        $user->update(['is_active' => false]);

        // Revocar todos los tokens del usuario desactivado
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => __('user_deactivated')
        ]);
    }

    // ========== CONFIGURACIÓN DEL SISTEMA ==========
    
    /** Devuelve la configuración institucional guardada o los valores de marca predeterminados. */
    public function getConfig()
    {
        $config = InstitutionConfig::first();

        if (!$config) {
            return response()->json([
                'institution_name' => 'Aulamate',
                'primary_color' => '#004AC6',
                'secondary_color' => '#006C49',
            ]);
        }

        return response()->json($config);
    }

    /**
     * Configuración pública (solo branding). No expone metadata sensible
     * como email_notifications, backup_frequency ni last_backup.
     */
    public function publicConfig()
    {
        $config = InstitutionConfig::first();

        return response()->json([
            'institution_name' => $config?->institution_name ?? 'Aulamate',
            'primary_color' => $config?->primary_color ?? '#004AC6',
            'secondary_color' => $config?->secondary_color ?? '#006C49',
            'tertiary_color' => $config?->tertiary_color ?? null,
            'background_color' => $config?->background_color ?? null,
            'surface_color' => $config?->surface_color ?? null,
            'logo' => $config?->logo ?? null,
            'allow_tab_switches' => $config?->allow_tab_switches ?? true,
        ]);
    }

    /** Valida y guarda la marca institucional y la configuración del sistema. */
    public function updateConfig(Request $request)
    {
        $config = InstitutionConfig::first() ?? InstitutionConfig::create([
            'id' => Str::uuid(),
            'institution_name' => 'Aulamate'
        ]);

        $validated = $request->validate([
            'institution_name' => 'sometimes|string|max:255',
            'primary_color' => 'sometimes|string|max:7',
            'secondary_color' => 'sometimes|string|max:7',
            'tertiary_color' => 'sometimes|string|max:7',
            'background_color' => 'sometimes|string|max:7',
            'surface_color' => 'sometimes|string|max:7',
            'logo' => 'nullable|string|max:2048',
            'email_notifications' => 'nullable|array',
            'backup_frequency' => 'nullable|string',
            'allow_tab_switches' => 'sometimes|boolean'
        ]);

        $config->update($validated);

        return response()->json([
            'message' => __('config_updated'),
            'config' => $config
        ]);
    }

    // ========== PERÍODOS ACADÉMICOS ==========
    
    /** Devuelve los períodos académicos ordenados por fecha de inicio descendente. */
    public function getPeriods()
    {
        $periods = AcademicPeriod::orderBy('start_date', 'desc')->get();
        return response()->json($periods);
    }

    /** Crea un período académico y, si se solicita, lo establece como el único activo. */
    public function createPeriod(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string'
        ]);

        // Desactivar otros períodos si este es activo
        if ($request->has('is_active') && $request->is_active) {
            AcademicPeriod::where('is_active', true)->update(['is_active' => false]);
        }

        $period = AcademicPeriod::create([
            'id' => Str::uuid(),
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->is_active ?? false
        ]);

        return response()->json([
            'message' => __('period_created'),
            'period' => $period
        ], 201);
    }

    /** Actualiza un período y desactiva los demás si se marca como activo. */
    public function updatePeriod(Request $request, $id)
    {
        $period = AcademicPeriod::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string'
        ]);

        // Desactivar otros períodos si este se activa
        if (isset($validated['is_active']) && $validated['is_active']) {
            AcademicPeriod::where('is_active', true)
                ->where('id', '!=', $id)
                ->update(['is_active' => false]);
        }

        $period->update($validated);

        return response()->json([
            'message' => __('period_updated'),
            'period' => $period
        ]);
    }

    /** Elimina el período académico indicado. */
    public function deletePeriod($id)
    {
        $period = AcademicPeriod::findOrFail($id);
        $period->delete();

        return response()->json([
            'message' => __('period_deleted')
        ]);
    }

    // ========== DASHBOARD ADMIN ==========
    
    /** Devuelve estadísticas del panel administrativo y los diez usuarios más recientes. */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_students' => User::where('role', Role::STUDENT)->count(),
            'total_teachers' => User::where('role', Role::TEACHER)->count(),
            'total_lessons' => Lesson::count(),
            'published_lessons' => Lesson::where('is_published', true)->count(),
            'total_evaluations' => Evaluation::count(),
            'active_period' => AcademicPeriod::where('is_active', true)->first()
        ];

        $recentUsers = User::query()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_users' => $recentUsers
        ]);
    }

    // ========== IMPORTAR / EXPORTAR USUARIOS ==========

    /** Importa usuarios desde CSV y devuelve el total importado y los errores por fila. */
    public function importUsers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240'
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        $imported = 0;
        $errors = [];

        $header = fgetcsv($handle, 0, ',');
        $headerMap = array_map('strtolower', array_map('trim', $header));

        $nameIdx = array_search('full_name', $headerMap) ?? array_search('nombre', $headerMap);
        $emailIdx = array_search('email', $headerMap);
        $roleIdx = array_search('role', $headerMap) ?? array_search('rol', $headerMap);
        $passwordIdx = array_search('password', $headerMap) ?? array_search('contraseña', $headerMap);

        if ($nameIdx === false || $emailIdx === false || $roleIdx === false) {
            return response()->json([
                'message' => __('csv_invalid_columns')
            ], 422);
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowNum++;
            $fullName = $row[$nameIdx] ?? null;
            $email = $row[$emailIdx] ?? null;
            $roleName = $row[$roleIdx] ?? null;
            $password = $passwordIdx !== false ? ($row[$passwordIdx] ?? null) : null;

            if (!$fullName || !$email || !$roleName) {
                $errors[] = __('import_row_incomplete_data', ['row' => $rowNum]);
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $errors[] = __('import_row_email_exists', ['row' => $rowNum, 'email' => $email]);
                continue;
            }

            $role = strtolower($roleName);
            if (!in_array($role, [Role::TEACHER, Role::STUDENT, Role::DIRECTOR, Role::COORDINATOR], true)) {
                $errors[] = __('import_row_invalid_role', ['row' => $rowNum, 'role' => $roleName]);
                continue;
            }

            User::create([
                'id' => Str::uuid(),
                'full_name' => $fullName,
                'email' => $email,
                'password' => Hash::make($password ?: Str::random(12)),
                'role' => $role,
                'is_active' => true,
                'provider' => 'email'
            ]);
            $imported++;
        }

        fclose($handle);

        return response()->json([
            'message' => __('users_imported_count', ['count' => $imported]),
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    /** Transmite un CSV con los usuarios, opcionalmente filtrados por rol. */
    public function exportUsers(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            if (in_array($request->role, [Role::ADMIN, Role::TEACHER, Role::STUDENT, Role::DIRECTOR, Role::COORDINATOR], true)) {
                $query->where('role', $request->role);
            }
        }

        $users = $query->orderBy('full_name')->get();

        $filename = __('export_filename', ['date' => now()->format('Y-m-d_His')]);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ];

        $callback = function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', __('csv_header_full_name'), __('csv_header_email'), __('csv_header_role'), __('csv_header_active'), __('csv_header_institution'), __('csv_header_created')]);
            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->id,
                    $this->sanitizeCsvValue($user->full_name),
                    $this->sanitizeCsvValue($user->email),
                    $this->sanitizeCsvValue($user->role->name ?? ''),
                    $user->is_active ? __('csv_yes') : __('csv_no'),
                    $this->sanitizeCsvValue($user->institution ?? ''),
                    $user->created_at->format('Y-m-d')
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ========== COPIAS DE SEGURIDAD ==========

    /** Crea un respaldo de la base de datos y devuelve nombre, tamaño y fecha. */
    public function createBackup()
    {
        $dbConfig = config('database.connections.mysql');

        if (!$dbConfig) {
            return response()->json(['message' => __('mysql_config_not_found')], 500);
        }

        $filename = 'backup_' . now()->format('Y-m-d_His') . '.sql';
        $backupPath = storage_path('app/backups');

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0750, true);
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s %s > %s 2>&1',
            escapeshellarg($this->resolveMysqldumpBinary()),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($backupPath . '/' . $filename)
        );

        $oldPwd = getenv('MYSQL_PWD');
        putenv('MYSQL_PWD=' . $password);

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($oldPwd === false) {
            putenv('MYSQL_PWD');
        } else {
            putenv('MYSQL_PWD=' . $oldPwd);
        }

        if ($returnCode !== 0) {
            report('Backup failed: ' . implode("\n", $output));
            return response()->json([
                'message' => __('backup_create_error')
            ], 500);
        }

        $config = InstitutionConfig::first();
        if ($config) {
            $config->update(['last_backup' => now()]);
        }

        return response()->json([
            'message' => __('backup_created'),
            'filename' => $filename,
            'size' => filesize($backupPath . '/' . $filename),
            'created_at' => now()
        ]);
    }

    /** Devuelve los datos del respaldo más reciente o indica que no hay respaldos. */
    public function getLastBackup()
    {
        $backupPath = storage_path('app/backups');

        if (!file_exists($backupPath)) {
            return response()->json([
                'backup' => null,
                'message' => __('no_backups')
            ]);
        }

        $files = glob($backupPath . '/backup_*.sql');
        if (empty($files)) {
            return response()->json([
                'backup' => null,
                'message' => __('no_backups')
            ]);
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latest = $files[0];
        $filename = basename($latest);

        return response()->json([
            'backup' => [
                'filename' => $filename,
                'size' => filesize($latest),
                'created_at' => date('Y-m-d H:i:s', filemtime($latest))
            ]
        ]);
    }

    /** Descarga un respaldo de base de datos tras validar el nombre de archivo. */
    public function downloadBackup($filename)
    {
        $safeFilename = basename($filename);

        if (!preg_match('/^backup_[\w\-]+\.sql$/', $safeFilename)) {
            return response()->json(['message' => __('invalid_filename')], 400);
        }

        $backupDir = storage_path('app/backups');
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $safeFilename;

        $realPath = realpath($backupPath);

        if ($realPath === false || strpos($realPath, realpath($backupDir)) !== 0) {
            return response()->json(['message' => __('backup_not_found')], 404);
        }

        if (!file_exists($realPath)) {
            return response()->json(['message' => __('backup_not_found')], 404);
        }

        return response()->download($realPath, $safeFilename, [
            'Content-Type' => 'application/sql'
        ]);
    }

    private function sanitizeCsvValue(string $value): string
    {
        $dangerousPrefixes = ['=', '+', '-', '@', "\t", "\r"];
        foreach ($dangerousPrefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $value = "'" . $value;
                break;
            }
        }
        return $value;
    }

    private function resolveMysqldumpBinary(): string
    {
        $binaryPath = config('database.connections.mysql.dump.dump_binary_path', '');
        $binary = trim((string) $binaryPath) !== ''
            ? rtrim($binaryPath, '\\/') . DIRECTORY_SEPARATOR . 'mysqldump'
            : 'mysqldump';

        if (DIRECTORY_SEPARATOR === '\\') {
            $binary .= '.exe';
        }

        return $binary;
    }
}