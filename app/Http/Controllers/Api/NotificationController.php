<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\PushNotificationService;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Consulta y actualiza notificaciones y ofrece utilidades para crear avisos y notificaciones push.
 */
class NotificationController extends Controller
{
    /**
     * Listar notificaciones del usuario
     */
    public function index(Request $request)
    {
        $query = Notification::where('user_id', Auth::id());

        if ($request->has('unread') && $request->unread) {
            $query->where('is_read', false);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 20), 50));

        return response()->json($notifications);
    }

    /**
     * Contar notificaciones no leídas
     */
    public function unreadCount()
    {
        $count = Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => __('notification_marked_as_read'),
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => __('notification_all_marked_as_read'),
        ]);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'message' => __('notification_deleted'),
        ]);
    }

    /**
     * Eliminar todas las notificaciones leídas
     */
    public function deleteRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', true)
            ->delete();

        return response()->json([
            'message' => __('notification_read_deleted'),
        ]);
    }

    /**
     * Crear notificación (in-app)
     */
    public static function createNotification($userId, $title, $message, $type = 'info', $link = null)
    {
        return Notification::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => false,
        ]);
    }

    /**
     * Crear notificación + enviar push (in-app + push)
     */
    public static function createAndPush($userId, $title, $message, $type = 'info', $link = null)
    {
        // Crear in-app
        $notification = Notification::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => false,
        ]);

        // Enviar push (fire and forget)
        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUser($userId, $title, $message, [
                'notification_id' => $notification->id,
                'type' => $type,
                'link' => $link ?? '',
            ]);
        } catch (\Exception $e) {
            report($e);
        }

        // Enviar web push (navegador, VAPID)
        try {
            $webPush = app(WebPushService::class);
            $webPush->sendToUser($userId, $title, $message, [
                'notification_id' => $notification->id,
                'type' => $type,
                'link' => $link ?? '',
            ]);
        } catch (\Exception $e) {
            report($e);
        }

        return $notification;
    }

    /**
     * Crear notificación para múltiples usuarios (in-app)
     */
    public static function createBulkNotifications($userIds, $title, $message, $type = 'info', $link = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'id' => Str::uuid(),
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return Notification::insert($notifications);
    }

    /**
     * Crear notificaciones bulk + push (in-app + push)
     */
    public static function createBulkAndPush(array $userIds, $title, $message, $type = 'info', $link = null)
    {
        self::createBulkNotifications($userIds, $title, $message, $type, $link);

        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUsers($userIds, $title, $message, [
                'type' => $type,
                'link' => $link ?? '',
            ]);
        } catch (\Exception $e) {
            report($e);
        }

        // Web push por usuario (VAPID)
        try {
            $webPush = app(WebPushService::class);
            foreach ($userIds as $userId) {
                $webPush->sendToUser($userId, $title, $message, [
                    'type' => $type,
                    'link' => $link ?? '',
                ]);
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
