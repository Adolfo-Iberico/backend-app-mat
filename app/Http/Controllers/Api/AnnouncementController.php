<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Publica y consulta anuncios globales del sistema gestionados por el administrador.
 */
class AnnouncementController extends Controller
{
    /** Lista los anuncios activos visibles para cualquier usuario autenticado. */
    public function index()
    {
        $announcements = Announcement::active()
            ->with('author:id,full_name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $announcements]);
    }

    /** Lista todos los anuncios, incluyendo los inactivos (solo admin). */
    public function adminIndex()
    {
        $announcements = Announcement::with('author:id,full_name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $announcements->items(), 'meta' => [
            'current_page' => $announcements->currentPage(),
            'total' => $announcements->total(),
        ]]);
    }

    /** Crea un anuncio y, si se solicita, notifica a todos los usuarios activos. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'type' => 'nullable|in:info,warning,success',
            'is_active' => 'nullable|boolean',
            'notify' => 'nullable|boolean',
        ]);

        $announcement = Announcement::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'body' => $validated['body'],
            'type' => $validated['type'] ?? Announcement::TYPE_INFO,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => Auth::id(),
        ]);

        // Difusión: crea una notificación en la aplicación para cada usuario activo.
        if ($announcement->is_active && ($validated['notify'] ?? true)) {
            $this->broadcast($announcement);
        }

        return response()->json([
            'message' => __('announcement_created'),
            'data' => $announcement->load('author:id,full_name'),
        ], 201);
    }

    /** Actualiza el contenido o el estado de un anuncio existente. */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string|max:5000',
            'type' => 'sometimes|in:info,warning,success',
            'is_active' => 'sometimes|boolean',
        ]);

        $announcement->update($validated);

        return response()->json([
            'message' => __('announcement_updated'),
            'data' => $announcement->fresh()->load('author:id,full_name'),
        ]);
    }

    /** Elimina un anuncio. */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json(['message' => __('announcement_deleted')]);
    }

    /** Crea la notificación en la aplicación para todos los usuarios activos. */
    private function broadcast(Announcement $announcement): void
    {
        $users = User::where('is_active', true)->select('id')->get();

        foreach ($users as $user) {
            Notification::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'title' => $announcement->title,
                'message' => Str::limit($announcement->body, 180),
                'type' => $announcement->type,
                'category' => 'announcement',
                'is_read' => false,
                'link' => '/announcements/'.$announcement->id,
                'created_by' => $announcement->created_by,
            ]);
        }
    }
}
