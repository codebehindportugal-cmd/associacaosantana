<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Sponsor;
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
            'patrocinadores' => $this->patrocinadores(),
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

    private function patrocinadores(): Collection
    {
        try {
            return Sponsor::where('ativo', true)
                ->where('mostrar_no_slider', true)
                ->orderBy('ordem')
                ->orderBy('empresa')
                ->get();
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
            'facebookPostUrl' => $evento->facebook_post_url,
            'badge' => $evento->badge ?: ($this->jaPassou([
                'data_inicio' => optional($evento->data_inicio)->toDateString(),
                'data_fim' => optional($evento->data_fim)->toDateString(),
            ]) ? 'Evento realizado' : 'Proximo evento'),
            'description' => $evento->descricao,
            'schedule' => $evento->programa ?: [],
            'stats' => $this->statsEvento($evento),
            'media' => $evento->media->map(fn ($media) => [
                'tipo' => $media->tipo,
                'caminho' => $media->caminho,
                'titulo' => $media->titulo,
                'origem' => $media->origem,
                'url_origem' => $media->url_origem,
            ])->values(),
            'data_inicio' => optional($evento->data_inicio)->toDateString(),
            'data_fim' => optional($evento->data_fim)->toDateString(),
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
        // Um evento só "passou" depois do ÚLTIMO dia (data_fim quando existe)
        $ultimoDia = $evento['data_fim'] ?? $evento['data_inicio'] ?? null;

        if (! $ultimoDia) {
            return false;
        }

        return Carbon::parse($ultimoDia)->endOfDay()->isPast();
    }

}
