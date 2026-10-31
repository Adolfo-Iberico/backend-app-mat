<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestiona suscripciones Web Push del navegador y expone la configuración de entrega.
 */
class PushSubscriptionController extends Controller
{
    private WebPushService $webPush;

    public function __construct(WebPushService $webPush)
    {
        $this->webPush = $webPush;
    }

    /**
     * Guardar una suscripción Web Push del navegador.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:500',
            'p256dh' => 'required|string|max:255',
            'auth' => 'required|string|max:255',
            'user_agent' => 'nullable|string|max:500',
        ]);

        $subscription = PushSubscription::where('token', $validated['endpoint'])->first();

        if ($subscription && (
            $subscription->platform !== 'web'
            || $subscription->user_id !== Auth::id()
        )) {
            return response()->json([
                'message' => 'This push endpoint is already registered to another account or device type.',
            ], 409);
        }

        $subscription ??= new PushSubscription;
        $subscription->fill([
            'user_id' => Auth::id(),
            'token' => $validated['endpoint'],
            'platform' => 'web',
            'p256dh' => $validated['p256dh'],
            'auth' => $validated['auth'],
            'device_name' => isset($validated['user_agent'])
                ? mb_substr($validated['user_agent'], 0, 255)
                : null,
            'is_active' => true,
        ]);
        $subscription->save();

        return response()->json([
            'message' => __('push_subscribed'),
            'subscription' => ['id' => $subscription->id],
        ], 201);
    }

    /**
     * Eliminar una suscripción Web Push.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:500',
        ]);

        PushSubscription::where('user_id', Auth::id())
            ->where('platform', 'web')
            ->where('token', $validated['endpoint'])
            ->delete();

        return response()->json(['message' => __('push_unsubscribed')]);
    }

    /**
     * Enviar una notificación de prueba al propio usuario.
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:500',
        ]);

        $sent = $this->webPush->sendToUser(
            Auth::id(),
            $validated['title'] ?? __('push_test_title'),
            $validated['body'] ?? __('push_test_body'),
        );

        return response()->json([
            'message' => __('push_test_sent'),
            'sent' => $sent,
        ]);
    }

    /**
     * Estado de configuración (para que el frontend sepa si habilitar el botón).
     */
    public function config()
    {
        return response()->json([
            'enabled' => $this->webPush->isConfigured(),
            'public_key' => $this->webPush->publicKey(),
        ]);
    }
}
