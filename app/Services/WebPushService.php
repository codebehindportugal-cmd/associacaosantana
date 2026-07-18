<?php

namespace App\Services;

use App\Models\Reserva;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function enviarChamada(Reserva $reserva): bool
    {
        if (! $reserva->temPushSubscription()) {
            return false;
        }

        $vapidPublic  = config('webpush.vapid_public');
        $vapidPrivate = config('webpush.vapid_private');

        if (! $vapidPublic || ! $vapidPrivate) {
            \Log::warning('[WebPush] VAPID keys não configuradas no .env');
            return false;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject'    => config('app.url'),
                    'publicKey'  => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ];

            $webPush = new WebPush($auth);

            $subscription = Subscription::create($reserva->push_subscription);

            $payload = json_encode([
                'title' => 'Associação de Santana 🎉',
                'body'  => 'A sua vez chegou! Dirija-se ao balcão.',
                'icon'  => '/favicon.ico',
                'badge' => '/favicon.ico',
                'tag'   => 'chamada-reserva-' . $reserva->id,
            ]);

            $webPush->queueNotification($subscription, $payload);

            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    \Log::warning('[WebPush] Falha ao enviar: ' . $report->getReason());
                    // Subscrição expirou ou é inválida — limpar
                    if ($report->isSubscriptionExpired()) {
                        $reserva->update(['push_subscription' => null]);
                    }
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            \Log::error('[WebPush] Exceção: ' . $e->getMessage());
            return false;
        }
    }
}
