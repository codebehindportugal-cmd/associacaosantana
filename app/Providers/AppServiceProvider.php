<?php

namespace App\Providers;

use App\Models\CaixaDiaria;
use App\Models\Categoria;
use App\Models\Configuracao;
use App\Models\Cota;
use App\Models\Evento;
use App\Models\EventoMedia;
use App\Models\Impressora;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PosSession;
use App\Models\Produto;
use App\Models\Reserva;
use App\Models\SitePage;
use App\Models\Socio;
use App\Models\Sponsor;
use App\Models\SponsorshipRequest;
use App\Models\User;
use App\Models\ZonaMapa;
use App\Observers\AuditObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        collect([
            CaixaDiaria::class,
            Categoria::class,
            Configuracao::class,
            Cota::class,
            Evento::class,
            EventoMedia::class,
            Impressora::class,
            Mesa::class,
            Pedido::class,
            PedidoItem::class,
            PosSession::class,
            Produto::class,
            Reserva::class,
            SitePage::class,
            Socio::class,
            Sponsor::class,
            SponsorshipRequest::class,
            User::class,
            ZonaMapa::class,
        ])->each(fn (string $model) => $model::observe(AuditObserver::class));
    }
}
