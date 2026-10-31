<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Registra, elimina y lista los tokens de notificación push del usuario actual.
 */
class DeviceController extends Controller
{
    /**
     * Registrar token de dispositivo para push notifications
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'platform' => 'required|in:android,ios,web',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
        ]);

        $userId = Auth::id();

        // Desactivar tokens duplicados del mismo usuario y plataforma
        DeviceToken::where('user_id', $userId)
            ->where('platform', $validated['platform'])
            ->where('token', $validated['token'])
            ->update(['is_active' => true, 'last_used_at' => now()]);

        // Crear o actualizar
        $device = DeviceToken::updateOrCreate(
            [
                'user_id' => $userId,
                'token' => $validated['token'],
                'platform' => $validated['platform'],
            ],
            [
                'device_name' => $validated['device_name'] ?? null,
                'device_model' => $validated['device_model'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => __('device_registered'),
            'device' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
            ],
        ]);
    }

    /**
     * Desactivar token de dispositivo
     */
    public function unregister(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('user_id', Auth::id())
            ->where('token', $validated['token'])
            ->update(['is_active' => false]);

        return response()->json([
            'message' => __('device_unregistered'),
        ]);
    }

    /**
     * Listar dispositivos registrados
     */
    public function list()
    {
        $devices = DeviceToken::forUser(Auth::id())
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'platform' => $d->platform,
                'device_name' => $d->device_name,
                'device_model' => $d->device_model,
                'is_active' => $d->is_active,
                'last_used_at' => $d->last_used_at,
                'created_at' => $d->created_at,
            ]);

        return response()->json([
            'devices' => $devices,
        ]);
    }
}
