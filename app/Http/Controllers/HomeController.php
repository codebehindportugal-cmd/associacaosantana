<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $eventos = $this->eventosPublicados();
        $upcoming = $eventos->filter(fn ($evento) => ! $this->jaPassou($evento))->values();
        $past = $eventos->filter(fn ($evento) => $this->jaPassou($evento))->values();

        return Inertia::render('Home', [
            'upcomingEvents' => $upcoming,
            'pastEvents' => $past,
        ]);
    }

    private function eventosPublicados(): Collection
    {
        try {
            return Evento::with('media')
                ->publicados()
                ->orderByDesc('destaque')
                ->orderByDesc('data_inicio')
                ->orderBy('ordem')
                ->get()
                ->map(fn (Evento $evento) => $this->formatarEvento($evento));
        } catch (Throwable) {
            return collect();
        }
    }

    private function formatarEvento(Evento $evento): array
    {
        return [
            'id' => $evento->id,
            'title' => $evento->titulo,
            'subtitle' => $evento->subtitulo ?: $evento->localizacao,
            'date' => $this->dataEvento($evento),
            'period' => $evento->periodo ?: optional($evento->data_inicio)->translatedFormat('F Y'),
            'location' => $evento->localizacao,
            'poster' => $evento->cartaz ?: '/images/santana-logo.png',
            'badge' => $evento->badge ?: ($this->jaPassou([
                'data_inicio' => optional($evento->data_inicio)->toDateString(),
            ]) ? 'Evento realizado' : 'Proximo evento'),
            'description' => $evento->descricao,
            'schedule' => $evento->programa ?: [],
            'stats' => $this->statsEvento($evento),
            'media' => $evento->media->map(fn ($media) => [
                'tipo' => $media->tipo,
                'caminho' => $media->caminho,
                'titulo' => $media->titulo,
            ])->values(),
            'data_inicio' => optional($evento->data_inicio)->toDateString(),
        ];
    }

    private function dataEvento(Evento $evento): ?string
    {
        if (! $evento->data_inicio) {
            return $evento->periodo;
        }

        if ($evento->data_fim && ! $evento->data_fim->isSameDay($evento->data_inicio)) {
            return $evento->data_inicio->format('d/m/Y').' a '.$evento->data_fim->format('d/m/Y');
        }

        return $evento->data_inicio->format('d/m/Y');
    }

    private function statsEvento(Evento $evento): array
    {
        return collect([
            $evento->periodo,
            $evento->localizacao,
            $evento->media->isNotEmpty() ? $evento->media->count().' ficheiros' : null,
        ])->filter()->values()->all();
    }

    private function jaPassou(array $evento): bool
    {
        if (empty($evento['data_inicio'])) {
            return false;
        }

        return Carbon::parse($evento['data_inicio'])->endOfDay()->isPast();
    }

}
