<?php

namespace App\Providers;

use App\Models\Cota;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Socio;
use App\Policies\CotaPolicy;
use App\Policies\MesaPolicy;
use App\Policies\PedidoPolicy;
use App\Policies\SocioPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Socio::class => SocioPolicy::class,
        Cota::class => CotaPolicy::class,
        Mesa::class => MesaPolicy::class,
        Pedido::class => PedidoPolicy::class,
    ];
}
