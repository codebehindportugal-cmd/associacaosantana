<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservaPublicaController extends Controller
{
    public function show(string $token): Response
    {
        $reserva = Reserva::where('token', $token)->firstOrFail();

        return Inertia::render('ReservaPublica', [
            'reserva' => [
                'id'                  => $reserva->id,
                'nome'                => $reserva->nome,
                'data'                => $reserva->data,
                'hora'                => substr((string) $reserva->hora, 0, 5),
                'pessoas'             => $reserva->pessoas,
                'estado'              => $reserva->estado,
                'tem_push'            => $reserva->temPushSubscription(),
                'token'               => $reserva->token,
            ],
            'vapidPublicKey' => config('webpush.vapid_public'),
        ]);
    }

    public function subscribe(Request $request, string $token): JsonResponse
    {
        $reserva = Reserva::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'endpoint'          => ['required', 'string', 'url'],
            'keys.auth'         => ['required', 'string'],
            'keys.p256dh'       => ['required', 'string'],
        ]);

        $reserva->update([
            'push_subscription' => [
                'endpoint'       => $data['endpoint'],
                'keys'           => [
                    'auth'   => $data['keys']['auth'],
                    'p256dh' => $data['keys']['p256dh'],
                ],
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $reserva = Reserva::where('token', $token)->firstOrFail();
        $reserva->update(['push_subscription' => null]);

        return response()->json(['ok' => true]);
    }
}
