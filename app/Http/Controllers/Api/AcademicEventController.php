<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Consulta y administra eventos del calendario académico para usuarios autenticados.
 */
class AcademicEventController extends Controller
{
    /**
     * Listar eventos (del usuario + públicos) en un rango de fechas.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'type' => 'nullable|string|in:activity,exam,holiday,meeting',
        ]);

        $query = AcademicEvent::forUser(Auth::id());

        if (!empty($validated['start']) && !empty($validated['end'])) {
            $query->inRange($validated['start'], $validated['end']);
        } elseif (!empty($validated['start'])) {
            $query->where('start_date', '>=', $validated['start']);
        } elseif (!empty($validated['end'])) {
            $query->where('start_date', '<=', $validated['end']);
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $events = $query->orderBy('start_date')->get();

        return response()->json([
            'events' => $events,
        ]);
    }

    /**
     * Crear un evento (docente o admin).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'type' => 'nullable|in:activity,exam,holiday,meeting',
            'color' => 'nullable|string|max:20',
            'all_day' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $event = AcademicEvent::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'type' => $validated['type'] ?? 'activity',
            'color' => $validated['color'] ?? null,
            'all_day' => $validated['all_day'] ?? false,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json([
            'message' => __('event_created'),
            'event' => $event,
        ], 201);
    }

    /**
     * Actualizar un evento propio.
     */
    public function update(Request $request, $id)
    {
        $event = AcademicEvent::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$event) {
            return response()->json(['message' => __('event_not_found')], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'type' => 'nullable|in:activity,exam,holiday,meeting',
            'color' => 'nullable|string|max:20',
            'all_day' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $event->update($validated);

        return response()->json([
            'message' => __('event_updated'),
            'event' => $event->fresh(),
        ]);
    }

    /**
     * Eliminar un evento propio.
     */
    public function destroy($id)
    {
        $deleted = AcademicEvent::where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => __('event_not_found')], 404);
        }

        return response()->json(['message' => __('event_deleted')]);
    }
}