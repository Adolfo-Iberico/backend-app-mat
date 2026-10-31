<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestiona hilos de debate y comentarios visibles según el rol y las relaciones académicas.
 */
class ForumController extends Controller
{
    /**
     * Hilos visibles para el usuario autenticado.
     * - Admin: todos los hilos del sistema (rol de moderación).
     * - Docente/coordinador/director: sus propios hilos y los avisos del admin.
     * - Estudiante: hilos de docentes con los que tiene relación académica,
     *   los avisos del admin y los hilos que él mismo abrió.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->roleName();

        $query = ForumThread::with(['teacher:id,full_name', 'author:id,full_name', 'lesson:id,title'])
            ->withCount('posts');

        // IDs de administradores: sus hilos funcionan como avisos globales.
        $adminIds = User::forRole(Role::ADMIN)->pluck('id');

        if ($role === 'admin') {
            // El administrador ve todo el foro.
        } elseif (in_array($role, ['teacher', 'coordinador', 'director'])) {
            $query->where(function ($q) use ($user, $adminIds) {
                $q->where('created_by', $user->id)
                    ->orWhereIn('created_by', $adminIds);
            });
        } else {
            $teacherIds = Evaluation::whereIn('id', EvaluationResult::where('user_id', $user->id)->pluck('evaluation_id'))
                ->pluck('teacher_id');
            $query->where(function ($q) use ($teacherIds, $adminIds, $user) {
                $q->whereIn('created_by', $teacherIds)
                    ->orWhereIn('created_by', $adminIds)
                    ->orWhere('author_id', $user->id);
            });
        }

        $threads = $query->orderByDesc('created_at')->get();

        return response()->json(['data' => $threads]);
    }

    /**
     * Detalle de un hilo con sus posts (accesible por el admin, el docente
     * responsable, el autor del hilo, estudiantes con relación con el docente
     * y los avisos del admin).
     */
    public function show(Request $request, $threadId)
    {
        $user = Auth::user();
        $thread = ForumThread::with(['teacher:id,full_name', 'author:id,full_name', 'lesson:id,title'])->findOrFail($threadId);

        $this->assertCanView($user, $thread);

        $posts = $thread->posts()
            ->with(['user:id,full_name,role_id', 'user.role:id,name'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $thread,
            'posts' => $posts,
        ]);
    }

    /**
     * Crear hilo.
     * - Docentes/coordinador/director: hilos propios (opcionalmente de una lección).
     * - Estudiantes: siempre asociados a una lección; el docente responsable
     *   es el docente de esa lección y el autor queda registrado en author_id.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $role = $user->roleName();
        $isStaff = in_array($role, ['teacher', 'coordinador', 'director', 'admin']);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:10000',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $lessonId = $validated['lesson_id'] ?? null;
        $teacherId = $user->id;

        if (! $isStaff) {
            if (! $lessonId) {
                return response()->json(['message' => 'Asocia tu pregunta a una lección'], 422);
            }

            $lesson = Lesson::findOrFail($lessonId);

            if (! $lesson->teacher_id) {
                return response()->json(['message' => 'La lección no tiene docente asignado'], 422);
            }

            $teacherId = $lesson->teacher_id;
        }

        $thread = ForumThread::create([
            'created_by' => $teacherId,
            'author_id' => $user->id,
            'lesson_id' => $lessonId,
            'title' => $validated['title'],
        ]);
        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'Hilo creado',
            'data' => $thread->load(['teacher:id,full_name', 'author:id,full_name', 'lesson:id,title']),
        ], 201);
    }

    /**
     * Comentar en un hilo (docente autor o estudiante con relación).
     */
    public function post(Request $request, $threadId)
    {
        $user = Auth::user();
        $thread = ForumThread::findOrFail($threadId);

        $this->assertCanView($user, $thread);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $post = ForumPost::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'Comentario publicado',
            'data' => $post->load(['user:id,full_name,role_id', 'user.role:id,name']),
        ], 201);
    }

    /**
     * Editar un comentario: su autor o el docente dueño del hilo.
     */
    public function updatePost(Request $request, $postId)
    {
        $user = Auth::user();
        $post = ForumPost::with('thread')->findOrFail($postId);

        $isAuthor = (string) $post->user_id === (string) $user->id;
        $isThreadOwner = $this->isThreadOwner($user, $post->thread);

        if (! $isAuthor && ! $isThreadOwner) {
            return response()->json(['message' => __('forum_post_forbidden')], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $post->update(['body' => $validated['body']]);

        return response()->json([
            'message' => __('forum_post_updated'),
            'data' => $post->fresh()->load(['user:id,full_name,role_id', 'user.role:id,name']),
        ]);
    }

    /**
     * Eliminar un comentario: su autor o el docente dueño del hilo.
     */
    public function deletePost($postId)
    {
        $user = Auth::user();
        $post = ForumPost::with('thread')->findOrFail($postId);

        $isAuthor = (string) $post->user_id === (string) $user->id;
        $isThreadOwner = $this->isThreadOwner($user, $post->thread);

        if (! $isAuthor && ! $isThreadOwner) {
            return response()->json(['message' => __('forum_post_forbidden')], 403);
        }

        $post->delete();

        return response()->json(['message' => __('forum_post_deleted')]);
    }

    /**
     * Cerrar un hilo (el docente autor).
     */
    public function close(Request $request, $threadId)
    {
        $user = Auth::user();
        $thread = ForumThread::findOrFail($threadId);

        if (! $this->isThreadOwner($user, $thread)) {
            return response()->json(['message' => 'Solo el docente autor puede cerrar el hilo'], 403);
        }

        $thread->update(['status' => ForumThread::STATUS_CLOSED]);

        return response()->json(['message' => 'Hilo cerrado', 'data' => $thread]);
    }

    /** Indica si el usuario actual es el docente dueño del hilo. */
    private function isThreadOwner($user, $thread): bool
    {
        if (! in_array($user->roleName(), ['teacher', 'coordinador', 'director'], true)) {
            return false;
        }

        return (string) $thread->teacher_id === (string) $user->id;
    }

    private function assertCanView($user, $thread)
    {
        $role = $user->roleName();

        // El administrador puede ver cualquier hilo.
        if ($role === 'admin') {
            return;
        }

        // El autor de un hilo siempre puede verlo y participar en él
        // (incluye estudiantes que abrieron su propia pregunta).
        if ((string) $thread->author_id === (string) $user->id) {
            return;
        }

        // Los hilos creados por administradores son avisos visibles para todos.
        // Se consulta directamente (no la relación cargada) porque el docente
        // se eager-loadea solo con id y full_name.
        $isAdminThread = User::where('id', $thread->teacher_id)
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->exists();
        if ($isAdminThread) {
            return;
        }

        if (in_array($role, ['teacher', 'coordinador', 'director'])) {
            if ((string) $thread->teacher_id !== (string) $user->id) {
                abort(403, 'No tienes permiso para ver este hilo');
            }

            return;
        }

        $teacherIds = Evaluation::whereIn('id', EvaluationResult::where('user_id', $user->id)->pluck('evaluation_id'))
            ->pluck('teacher_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (! in_array((string) $thread->teacher_id, $teacherIds)) {
            abort(403, 'No tienes permiso para ver este hilo');
        }
    }
}
