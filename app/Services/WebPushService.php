<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Envía notificaciones web push a las suscripciones activas de los usuarios. */
class WebPushService
{
    /**
     * Enviar notificación web push a todas las suscripciones activas de un usuario.
     */
    public function sendToUser(string $userId, string $title, string $body, array $data = []): int
    {
        $subscriptions = PushSubscription::forUser($userId)
            ->active()
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = $this->client();
        } catch (\RuntimeException $e) {
            Log::warning('Web push client no configurado: '.$e->getMessage());

            return 0;
        }

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->token,
                        'publicKey' => $subscription->p256dh,
                        'authToken' => $subscription->auth,
                        'contentEncoding' => 'aes128gcm',
                    ]),
                    json_encode([
                        'title' => $title,
                        'body' => $body,
                        'data' => $data,
                        'icon' => $data['icon'] ?? '/icons/icon-192.png',
                        'badge' => '/icons/icon-96.png',
                    ]),
                    [
                        'TTL' => 86400,
                        'urgency' => 'normal',
                        'VAPID' => [
                            'subject' => 'mailto:'.(config('services.webpush.subject_email') ?? 'admin@aulamate.com'),
                            'publicKey' => $this->publicKey(),
                            'privateKey' => $this->privateKey(),
                        ],
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Web push queue failed: '.$subscription->id, ['error' => $e->getMessage()]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::where('platform', 'web')
                    ->where('token', $report->getEndpoint())
                    ->update(['is_active' => false]);
            }
        }

        return $sent;
    }

    /**
     * Cliente WebPush con autenticación por defecto.
     */
    public function client(): WebPush
    {
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:'.(config('services.webpush.subject_email') ?? 'admin@aulamate.com'),
                'publicKey' => $this->publicKey(),
                'privateKey' => $this->privateKey(),
            ],
        ];

        return new WebPush($auth);
    }

    /** Clave pública VAPID configurada para el envío web push. */
    public function publicKey(): string
    {
        return config('services.webpush.vapid_public_key', '');
    }

    /** Clave privada VAPID configurada para el envío web push. */
    public function privateKey(): string
    {
        return config('services.webpush.vapid_private_key', '');
    }

    /** Indica si están configuradas ambas claves VAPID. */
    public function isConfigured(): bool
    {
        return ! empty($this->publicKey()) && ! empty($this->privateKey());
    }
}
