<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvaluationResult;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona el contenido y publicación de lecciones, recursos y recomendaciones de progreso.
 */
class LessonController extends Controller
{
    /**
     * Listar todas las lecciones con filtros
     */
    public function index(Request $request)
    {
        $query = Lesson::query()->with(['teacher']);
        // Filtros
        if ($request->has('difficulty') && $request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->has('unit') && $request->unit) {
            $query->where('unit', 'LIKE', '%' . $request->unit . '%');
        }
        if ($request->has('topic') && $request->topic) {
            $query->where('topic', 'LIKE', '%' . $request->topic . '%');
        }
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('content', 'LIKE', '%' . $request->search . '%');
            });
        }
        if ($request->has('tag') && $request->tag) {
            $query->whereJsonContains('tags', $request->tag);
        }
        // Si es estudiante, solo mostrar lecciones publicadas
        if (Auth::user()->isStudent()) {
            $query->where('is_published', true);

            // Aislamiento por grado: el estudiante solo ve las lecciones
            // de su propio grado (p. ej. 4to no ve las de 5to ni viceversa).
            $grade = $this->studentGrade(Auth::user());
            $query->where('grade', $grade ?? '__sin_grado__');
        }
        // Si es docente, mostrar sus lecciones
        if (Auth::user()->isTeacher()) {
            $query->where('teacher_id', Auth::id());
        }
        $lessons = $query->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 15), 50));
        // Agregar información de progreso para estudiantes (una sola query agrupada, evita N+1)
        if (Auth::user()->isStudent()) {
            $myProgress = LessonProgress::where('user_id', Auth::id())
                ->whereIn('lesson_id', $lessons->pluck('id'))
                ->get()
                ->keyBy('lesson_id');
            $lessons->getCollection()->transform(function ($lesson) use ($myProgress) {
                $lesson->user_progress = $myProgress->get($lesson->id);
                return $lesson;
            });
        }
        return response()->json([
            'success' => true,
            'data' => $lessons
        ]);
    }
    private function studentGrade($user): ?string
    {
        $raw = $user->salon?->grade ?: $user->grade;

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        if (preg_match('/^([1-5])/', trim($raw), $m)) {
            return ['1' => '1ro', '2' => '2do', '3' => '3ro', '4' => '4to', '5' => '5to'][$m[1]];
        }

        return null;
    }
    /**
     * Mostrar una lección específica con su contenido completo
     */
    public function show($id)
    {
        $lesson = Lesson::with(['teacher', 'evaluations'])
            ->findOrFail($id);

        // Verificar si el estudiante puede ver esta lección
        if (Auth::user()->isStudent()) {
            if (!$lesson->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => __('lesson_not_available')
                ], 403);
            }

            // Aislamiento por grado: prohibido ver lecciones de otro grado.
            if ($lesson->grade !== $this->studentGrade(Auth::user())) {
                return response()->json([
                    'success' => false,
                    'message' => __('lesson_not_available')
                ], 403);
            }
        }

        // Obtener progreso del estudiante
        if (Auth::user()->isStudent()) {
            $progress = LessonProgress::where('user_id', Auth::id())
                ->where('lesson_id', $id)
                ->first();

            if (!$progress) {
                $progress = LessonProgress::create([
                    'id' => Str::uuid(),
                    'user_id' => Auth::id(),
                    'lesson_id' => $id,
                    'progress' => 0,
                    'status' => LessonProgress::STATUS_NOT_STARTED,
                    'time_spent' => 0,
                    'last_position' => 0
                ]);
            }

            $lesson->user_progress = $progress;
        }

        // Incrementar contador de vistas
        $lesson->increment('views_count');

        return response()->json([
            'success' => true,
            'data' => $lesson
        ]);
    }

    /**
     * Contenido completo de una lección — optimizado para mobile
     * Retorna solo el contenido HTML/Markdown sin metadata pesada
     */
    public function content($id)
    {
        $lesson = Lesson::findOrFail($id);

        if (Auth::user()->isStudent()) {
            if (!$lesson->is_published) {
                return response()->json([
                    'success' => false,
                    'message' => __('lesson_not_available')
                ], 403);
            }

            // Aislamiento por grado también en el endpoint de contenido.
            if ($lesson->grade !== $this->studentGrade(Auth::user())) {
                return response()->json([
                    'success' => false,
                    'message' => __('lesson_not_available')
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'content' => $lesson->content,
                'estimated_time' => $lesson->estimated_time,
            ]
        ]);
    }

    /**
     * Crear una nueva lección
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'unit' => 'nullable|string|max:255',
            'topic' => 'nullable|string|max:255',
            'difficulty' => 'required|in:basic,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'estimated_time' => 'nullable|integer|min:1|max:999',
            'resources' => 'nullable|array',
            'resources.*.type' => 'required|in:pdf,video,image,link,audio',
            'resources.*.url' => 'required|string|url',
            'resources.*.title' => 'required|string|max:255',
            'order' => 'nullable|integer|min:0',
            'grade' => 'nullable|in:1ro,2do,3ro,4to,5to'
        ]);

        $validated['content'] = $this->sanitizeHtml($validated['content']);

        $lesson = Lesson::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'teacher_id' => Auth::id(),
            'unit' => $validated['unit'] ?? null,
            'topic' => $validated['topic'] ?? null,
            'difficulty' => $validated['difficulty'],
            'tags' => $validated['tags'] ?? [],
            'estimated_time' => $validated['estimated_time'] ?? 45,
            'resources' => $validated['resources'] ?? [],
            'order' => $validated['order'] ?? 0,
            'grade' => $validated['grade'] ?? null,
            'is_published' => false,
            'views_count' => 0
        ]);

        // Notificar a estudiantes sobre nueva lección
        $studentIds = User::where('role', Role::STUDENT)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
        if (!empty($studentIds)) {
            NotificationController::createBulkNotifications(
                $studentIds,
                __('notification_lesson_available_title'),
                __('notification_lesson_created_body', ['title' => $lesson->title]),
                'info',
                "/lessons/{$lesson->id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('lesson_created'),
            'data' => $lesson->load('teacher')
        ], 201);
    }

    /**
     * Subir un recurso local (archivo del PC del docente)
     */
    public function uploadResource(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower($file->getClientMimeType());

        $allowed = [
            'pdf' => ['pdf'],
            'image' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'],
            'video' => ['mp4', 'webm', 'mov', 'avi'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
        ];

        $type = null;
        foreach ($allowed as $candidateType => $extensions) {
            if (in_array($extension, $extensions)) {
                $type = $candidateType;
                break;
            }
        }

        if ($type === null) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_resource_type_invalid'),
            ], 422);
        }

        $sizeLimits = [
            'video' => 100 * 1024 * 1024,
            'image' => 10 * 1024 * 1024,
            'audio' => 20 * 1024 * 1024,
            'pdf' => 20 * 1024 * 1024,
        ];

        if ($file->getSize() > $sizeLimits[$type]) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_resource_too_large'),
            ], 422);
        }

        $name = Str::uuid() . '.' . $extension;
        $file->storeAs('lesson-resources', $name, 'public');

        return response()->json([
            'success' => true,
            'url' => $request->root() . '/storage/lesson-resources/' . $name,
            'type' => $type,
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
        ], 201);
    }

    /**
     * Actualizar una lección existente
     */
    public function update(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        // Verificar que el usuario sea el propietario o admin
        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_edit')
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'unit' => 'nullable|string|max:255',
            'topic' => 'nullable|string|max:255',
            'difficulty' => 'sometimes|in:basic,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'estimated_time' => 'nullable|integer|min:1|max:999',
            'resources' => 'nullable|array',
            'resources.*.type' => 'required|in:pdf,video,image,link,audio',
            'resources.*.url' => 'required|string|url',
            'resources.*.title' => 'required|string|max:255',
            'order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'grade' => 'nullable|in:1ro,2do,3ro,4to,5to'
        ]);

        if (isset($validated['content'])) {
            $validated['content'] = $this->sanitizeHtml($validated['content']);
        }

        $oldValues = $lesson->only(array_keys($validated));
        $lesson->update($validated);

        return response()->json([
            'success' => true,
            'message' => __('lesson_updated'),
            'data' => $lesson->load('teacher')
        ]);
    }

    /**
     * Eliminar una lección (soft delete)
     */
    public function destroy($id)
    {
        $lesson = Lesson::findOrFail($id);

        // Verificar que el usuario sea el propietario o admin
        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_delete')
            ], 403);
        }

        // Verificar si tiene evaluaciones asociadas
        if ($lesson->evaluations()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_cannot_delete_has_evaluations')
            ], 400);
        }

        $lesson->delete();

        return response()->json([
            'success' => true,
            'message' => __('lesson_deleted')
        ]);
    }

    /**
     * Publicar una lección
     */
    public function publish($id)
    {
        $lesson = Lesson::findOrFail($id);

        // Verificar que el usuario sea el propietario o admin
        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_publish')
            ], 403);
        }

        // Verificar que tenga contenido
        if (empty($lesson->content)) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_must_have_content_to_publish')
            ], 400);
        }

        $lesson->update([
            'is_published' => true,
            'published_at' => now()
        ]);

        // Notificar a estudiantes sobre lección publicada
        $studentIds = User::where('role', Role::STUDENT)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
        if (!empty($studentIds)) {
            NotificationController::createBulkNotifications(
                $studentIds,
                __('notification_lesson_published_title'),
                __('notification_lesson_published_body', ['title' => $lesson->title]),
                'info',
                "/lessons/{$lesson->id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('lesson_published'),
            'data' => $lesson
        ]);
    }

    /**
     * Despublicar una lección
     */
    public function unpublish($id)
    {
        $lesson = Lesson::findOrFail($id);

        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_unpublish')
            ], 403);
        }

        $lesson->update([
            'is_published' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => __('lesson_unpublished')
        ]);
    }

    /**
     * Obtener recursos de una lección
     */
    public function getResources($id)
    {
        $lesson = Lesson::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $lesson->resources ?? []
        ]);
    }

    /**
     * Agregar recurso a una lección
     */
    public function addResource(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_add_resources')
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:pdf,video,image,link,audio',
            'url' => 'required|string|url',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $resources = $lesson->resources ?? [];
        $resources[] = [
            'id' => Str::uuid(),
            'type' => $validated['type'],
            'url' => $validated['url'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'created_at' => now()
        ];

        $lesson->update(['resources' => $resources]);

        return response()->json([
            'success' => true,
            'message' => __('lesson_resource_added'),
            'data' => $resources
        ]);
    }

    /**
     * Eliminar recurso de una lección
     */
    public function removeResource($id, $resourceId)
    {
        $lesson = Lesson::findOrFail($id);

        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_delete_resources')
            ], 403);
        }

        $resources = array_filter($lesson->resources ?? [], function($resource) use ($resourceId) {
            return $resource['id'] !== $resourceId;
        });

        $lesson->update(['resources' => array_values($resources)]);

        return response()->json([
            'success' => true,
            'message' => __('lesson_resource_deleted'),
            'data' => array_values($resources)
        ]);
    }

    /**
     * Duplicar una lección
     */
    public function duplicate($id)
    {
        $originalLesson = Lesson::findOrFail($id);

        if ((string) $originalLesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_duplicate')
            ], 403);
        }

        $newLesson = $originalLesson->replicate();
        $newLesson->id = Str::uuid();
        $newLesson->title = $originalLesson->title . __('lesson_copy_suffix');
        $newLesson->is_published = false;
        $newLesson->views_count = 0;
        $newLesson->created_at = now();
        $newLesson->updated_at = now();
        $newLesson->save();

        return response()->json([
            'success' => true,
            'message' => __('lesson_duplicated'),
            'data' => $newLesson->load('teacher')
        ]);
    }

    /**
     * Obtener lecciones por unidad
     */
    public function getByUnit($unit)
    {
        $lessons = Lesson::where('unit', $unit)
            ->where('is_published', true)
            ->orderBy('order', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lessons
        ]);
    }

    /**
     * Obtener estadísticas de una lección (para docentes)
     */
    public function getStats($id)
    {
        $lesson = Lesson::findOrFail($id);

        if ((string) $lesson->teacher_id !== (string) Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => __('lesson_no_permission_view_stats')
            ], 403);
        }

        $stats = [
            'total_views' => $lesson->views_count ?? 0,
            'students_started' => LessonProgress::where('lesson_id', $id)->count(),
            'students_completed' => LessonProgress::where('lesson_id', $id)
                ->where('status', LessonProgress::STATUS_COMPLETED)
                ->count(),
            'average_progress' => LessonProgress::where('lesson_id', $id)->avg('progress') ?? 0,
            'average_time_spent' => LessonProgress::where('lesson_id', $id)->avg('time_spent') ?? 0,
            'completion_rate' => $this->calculateCompletionRate($id)
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Calcular tasa de finalización
     */
    private function calculateCompletionRate($lessonId)
    {
        $total = LessonProgress::where('lesson_id', $lessonId)->count();
        if ($total === 0) return 0;

        $completed = LessonProgress::where('lesson_id', $lessonId)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    private function sanitizeHtml(string $content): string
    {
        $allowed = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><ul><ol><li><a><img><blockquote><pre><code><table><thead><tbody><tr><th><td><div><span><sup><sub><hr>';
        return strip_tags($content, $allowed);
    }

    /**
     * Lecciones recomendadas según rendimiento del estudiante
     */
    public function recommended(Request $request)
    {
        $user = Auth::user();

        $averageScore = EvaluationResult::where('user_id', $user->id)
            ->where('status', 'completed')
            ->avg('score');

        if ($averageScore !== null && $averageScore >= 16) {
            $difficulty = 'advanced';
        } elseif ($averageScore !== null && $averageScore >= 12) {
            $difficulty = 'intermediate';
        } else {
            $difficulty = 'basic';
        }

        $completedLessonIds = LessonProgress::where('user_id', $user->id)
            ->where('status', LessonProgress::STATUS_COMPLETED)
            ->pluck('lesson_id')
            ->toArray();

        $lessons = Lesson::where('is_published', true)
            ->where('difficulty', $difficulty)
            ->whereNotIn('id', $completedLessonIds)
            ->with(['teacher'])
            ->orderBy('order', 'asc')
            ->limit(10)
            ->get();

        // Progreso del estudiante en las recomendadas (una sola query, evita N+1)
        $myProgress = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        $lessons->transform(function ($lesson) use ($myProgress) {
            $lesson->user_progress = $myProgress->get($lesson->id);
            return $lesson;
        });

        return response()->json([
            'success' => true,
            'message' => __('recommended_lessons'),
            'data' => [
                'difficulty' => $difficulty,
                'average_score' => round($averageScore ?? 0, 2),
                'lessons' => $lessons
            ]
        ]);
    }
}