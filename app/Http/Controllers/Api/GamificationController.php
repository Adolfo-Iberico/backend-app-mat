<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Expone resúmenes de logros del estudiante y permite sincronizar o revisar logros.
 */
class GamificationController extends Controller
{
    private GamificationService $service;

    public function __construct(GamificationService $service)
    {
        $this->service = $service;
    }

    /**
     * Resumen completo de gamificación del estudiante autenticado.
     */
    public function summary(Request $request)
    {
        $locale = in_array($request->input('locale'), ['es', 'en', 'qu']) ? $request->input('locale') : 'es';

        return response()->json([
            'gamification' => $this->service->gamificationSummary(Auth::user(), $locale),
        ]);
    }

    /**
     * Resumen de gamificación de un alumno, visible para docentes con
     * relación académica y para coordinadores/directores.
     */
    public function studentSummary(Request $request, $studentId)
    {
        $user = Auth::user();
        $student = User::findOrFail($studentId);

        if (!$student->isStudent()) {
            return response()->json(['message' => __('gamification_student_not_found')], 404);
        }

        if ($user->isTeacher() && !$user->isCoordinator() && !$user->isDirector()) {
            return response()->json(['message' => __('gamification_student_forbidden')], 403);
        }

        $locale = in_array($request->input('locale'), ['es', 'en', 'qu']) ? $request->input('locale') : 'es';

        return response()->json([
            'student' => $student->only('id', 'full_name'),
            'gamification' => $this->service->gamificationSummary($student, $locale),
        ]);
    }

    /**
     * Sincronizar la definición de logros con la BD (admin).
     */
    public function sync()
    {
        GamificationService::syncDefinitions();

        return response()->json([
            'message' => __('achievements_synced'),
            'count' => count(GamificationService::definitions()),
        ]);
    }

    /**
     * Re-evaluar logros del usuario autenticado.
     */
    public function check()
    {
        $newly = $this->service->checkAchievements(Auth::user());

        return response()->json([
            'new_unlocked' => array_map(fn ($a) => $a->slug, $newly),
        ]);
    }
}