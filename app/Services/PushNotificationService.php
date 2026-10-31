<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Envía notificaciones push FCM a dispositivos y crea notificaciones internas. */
class PushNotificationService
{
    private string $fcmUrl;

    private string $fcmKey;

    /** Carga las credenciales y el endpoint FCM desde la configuración. */
    public function __construct()
    {
        $this->fcmKey = config('services.fcm.key', '');
        $projectId = config('services.fcm.project_id', '');
        $this->fcmUrl = $projectId
            ? "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send"
            : '';
    }

    /**
     * Enviar notificación push a todos los dispositivos de un usuario
     */
    public function sendToUser(string $userId, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::forUser($userId)
            ->active()
            ->get();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($tokens as $device) {
            try {
                $this->sendToDevice($device, $title, $body, $data);
                $device->update(['last_used_at' => now()]);
                $sent++;
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'NotRegistered') || str_contains($e->getMessage(), 'InvalidRegistration')) {
                    $device->update(['is_active' => false]);
                }
                Log::warning('Push notification failed for device: '.$device->id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Enviar a un dispositivo específico (FCM v1 HTTP)
     */
    public function sendToDevice(DeviceToken $device, string $title, string $body, array $data = []): void
    {
        if (empty($this->fcmKey) || empty($this->fcmUrl)) {
            throw new \RuntimeException('FCM not configured — set FCM_SERVER_KEY and FCM_PROJECT_ID in .env');
        }

        $payload = [
            'message' => [
                'token' => $device->token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'aulamate_default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'badge' => 1,
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($this->fcmKey)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($this->fcmUrl, $payload);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Unknown FCM error');
            throw new \RuntimeException('FCM error: '.$error);
        }
    }

    /** Envía a varios usuarios y devuelve los totales enviados y fallidos. */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = ['sent' => 0, 'failed' => 0];

        foreach ($userIds as $userId) {
            $sent = $this->sendToUser($userId, $title, $body, $data);
            $results['sent'] += $sent;
            $results['failed'] += DeviceToken::forUser($userId)->active()->count() - $sent;
        }

        return $results;
    }

    /**
     * Enviar notificación y crear registro in-app
     */
    public function sendAndStore(string $userId, string $title, string $body, string $type = 'info', array $data = []): void
    {
        // Crear notificación in-app
        Notification::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'title' => $title,
            'message' => $body,
            'type' => $type,
            'link' => $data['link'] ?? null,
            'is_read' => false,
        ]);

        // Enviar push
        $this->sendToUser($userId, $title, $body, $data);
    }
}
