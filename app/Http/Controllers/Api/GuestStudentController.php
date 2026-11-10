<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\StudentReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Permite a apoderados autenticados consultar el progreso de estudiantes vinculados. */
class GuestStudentController extends Controller
{
    private const CAPTCHA_TTL_SECONDS = 300;

    private const CAPTCHA_MAX_ATTEMPTS = 5;

    /** Valida el CAPTCHA y devuelve el resumen académico de un estudiante vinculado. */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dni' => 'required|string|size:8|digits:8',
            'captcha_token' => 'required|string',
            'captcha_answer' => 'required|string',
        ]);

        if ($error = $this->verifyCaptcha($validated)) {
            return $error;
        }

        $student = $this->findLinkedStudent($validated['dni'], (string) $request->user()->id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar la consulta.',
            ], 404);
        }

        $profile = $student->studentProfile;

        $evaluationResults = DB::table('evaluation_results')
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_results.evaluation_id')
            ->where('evaluation_results.user_id', $student->id)
            ->where('evaluation_results.status', 'completed')
            ->orderByDesc('evaluation_results.completed_at')
            ->limit(10)
            ->select(
                'evaluations.title',
                'evaluation_results.score',
                'evaluation_results.completed_at as date'
            )
            ->get();

        $lessonProgressSummary = DB::table('lesson_progress')
            ->where('user_id', $student->id)
            ->selectRaw("
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started
            ")
            ->first();

        // Promedio real calculado desde las evaluaciones completadas
        $averageScore = round((float) DB::table('evaluation_results')
            ->where('user_id', $student->id)
            ->where('status', 'completed')
            ->avg('score'), 1);

        $totalLessonsCompleted = (int) ($lessonProgressSummary->completed ?? 0);
        $currentStreak = $this->computeStreak($student->id);

        $gradesByArea = DB::table('evaluation_results')
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_results.evaluation_id')
            ->join('lessons', 'lessons.id', '=', 'evaluations.lesson_id')
            ->where('evaluation_results.user_id', $student->id)
            ->where('evaluation_results.status', 'completed')
            ->select(
                'lessons.unit as area_name',
                DB::raw('AVG(evaluation_results.score) as average_score')
            )
            ->groupBy('lessons.unit')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'name' => $student->full_name,
                    'grade' => $student->grade,
                    'institution' => $student->institution,
                ],
                'average_score' => $averageScore,
                'total_lessons_completed' => $totalLessonsCompleted,
                'current_streak' => $currentStreak,
                'evaluation_results' => $evaluationResults,
                'lesson_progress_summary' => [
                    'completed' => $lessonProgressSummary->completed ?? 0,
                    'in_progress' => $lessonProgressSummary->in_progress ?? 0,
                    'not_started' => $lessonProgressSummary->not_started ?? 0,
                ],
                'grades_by_area' => $gradesByArea,
            ],
        ]);
    }

    /**
     * Valida el CAPTCHA (token, expiración y respuesta) con límite de intentos.
     * Devuelve null si es correcto o la respuesta de error correspondiente.
     */
    private function verifyCaptcha(array $validated): ?JsonResponse
    {
        $decoded = $this->decodeCaptchaToken($validated['captcha_token']);
        if (! $decoded) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha inválido o expirado. Intente nuevamente.',
            ], 422);
        }

        if (time() > $decoded['expires_at']) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha expirado. Intente nuevamente.',
            ], 422);
        }

        $usedKey = 'captcha_used:'.$decoded['jti'];
        $remainingSeconds = max(1, $decoded['expires_at'] - time());
        if (! Cache::add($usedKey, true, now()->addSeconds($remainingSeconds))) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha inválido o expirado. Intente nuevamente.',
            ], 422);
        }

        if (strtoupper($validated['captcha_answer']) !== strtoupper($decoded['code'])) {
            $ipKey = 'captcha_attempts:'.request()->ip();
            $dniKey = 'captcha_attempts_dni:'.$validated['dni'];
            $attempts = (int) Cache::get($ipKey, 0) + 1;
            Cache::put($ipKey, $attempts, now()->addMinutes(10));
            $dniAttempts = (int) Cache::get($dniKey, 0) + 1;
            Cache::put($dniKey, $dniAttempts, now()->addMinutes(10));
            if ($attempts >= self::CAPTCHA_MAX_ATTEMPTS || $dniAttempts >= self::CAPTCHA_MAX_ATTEMPTS) {
                Cache::forget($ipKey);
                Cache::forget($dniKey);

                return response()->json([
                    'success' => false,
                    'message' => 'Demasiados intentos. Solicite un nuevo captcha.',
                ], 429);
            }

            return response()->json([
                'success' => false,
                'message' => 'Captcha inválido. Intente nuevamente.',
            ], 422);
        }

        Cache::forget('captcha_attempts:'.request()->ip());
        Cache::forget('captcha_attempts_dni:'.$validated['dni']);

        return null;
    }

    /**
     * Exporta el reporte del estudiante en PDF, Excel o CSV tras validar DNI y CAPTCHA.
     * Consulta pública sin autenticación para padres/tutores.
     */
    public function exportLookup(Request $request)
    {
        $validated = $request->validate([
            'dni' => 'required|string|size:8|digits:8',
            'captcha_token' => 'required|string',
            'captcha_answer' => 'required|string',
            'format' => 'required|in:pdf,excel,csv',
        ]);

        if ($error = $this->verifyCaptcha($validated)) {
            return $error;
        }

        $student = $this->findLinkedStudent($validated['dni'], (string) $request->user()->id);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo completar la consulta.',
            ], 404);
        }

        $service = app(StudentReportService::class);
        $filename = 'reporte-estudiante-'.$validated['dni'];

        return match ($validated['format']) {
            'pdf' => $service->pdf($student->id, $filename.'.pdf'),
            'excel' => $service->excel($student->id, $filename.'.xlsx'),
            'csv' => $service->csv($student->id, $filename.'.csv'),
        };
    }

    private function computeStreak(string $userId): int
    {
        $dates = DB::table('lesson_progress')
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->pluck('completed_at')
            ->merge(
                DB::table('evaluation_results')
                    ->where('user_id', $userId)
                    ->where('status', 'completed')
                    ->whereNotNull('completed_at')
                    ->pluck('completed_at')
            );

        if ($dates->isEmpty()) {
            return 0;
        }

        $days = $dates
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->sortDesc()
            ->values();

        $streak = 1;
        $previous = Carbon::parse($days[0]);
        for ($i = 1; $i < $days->count(); $i++) {
            $current = Carbon::parse($days[$i]);
            if ($previous->copy()->subDay()->eq($current)) {
                $streak++;
                $previous = $current;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function findLinkedStudent(string $dni, string $parentId): ?User
    {
        return User::query()
            ->where('dni', $dni)
            ->whereHas('role', fn ($query) => $query->where('name', Role::STUDENT))
            ->whereExists(function ($query) use ($parentId): void {
                $query->selectRaw('1')
                    ->from('parent_student')
                    ->whereColumn('parent_student.student_id', 'users.id')
                    ->where('parent_student.parent_id', $parentId);
            })
            ->first();
    }

    /** Genera un token CAPTCHA cifrado y la imagen correspondiente. */
    public function generateCaptcha(): JsonResponse
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $captcha = '';
        for ($i = 0; $i < 6; $i++) {
            $captcha .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $expiresAt = time() + self::CAPTCHA_TTL_SECONDS;
        $token = Crypt::encryptString(json_encode([
            'code' => $captcha,
            'expires_at' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ]));

        return response()->json([
            'success' => true,
            'captcha_token' => $token,
            'captcha_image' => $this->renderCaptchaPng($captcha),
            'captcha_image_url' => null,
            'expires_in' => self::CAPTCHA_TTL_SECONDS,
        ]);
    }

    private function decodeCaptchaToken(?string $token): ?array
    {
        if (! $token) {
            return null;
        }
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return null;
        }
        if (
            ! is_array($payload)
            || empty($payload['code'])
            || ! is_numeric($payload['expires_at'] ?? null)
            || empty($payload['jti'])
        ) {
            return null;
        }

        return $payload;
    }

    /**
     * Renderiza el captcha como imagen PNG (raster) con ruido.
     * A diferencia del SVG anterior, la respuesta no es texto seleccionable/máquina,
     * por lo que exige OCR real para automatizar.
     */
    private function renderCaptchaPng(string $code): string
    {
        $width = 160;
        $height = 50;

        $img = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($img, 241, 245, 249);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        $noise = imagecolorallocate($img, 203, 213, 225);

        for ($i = 0; $i < 6; $i++) {
            imageline(
                $img,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $noise
            );
        }

        for ($i = 0; $i < 140; $i++) {
            imagesetpixel($img, random_int(0, $width), random_int(0, $height), $noise);
        }

        $charColor = imagecolorallocate($img, 30, 41, 59);
        foreach (str_split($code) as $i => $char) {
            $x = 15 + ($i * 27);
            $y = random_int(10, 22);
            imagestring($img, 5, $x, $y, $char, $charColor);
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
