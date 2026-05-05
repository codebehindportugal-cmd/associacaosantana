<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Socio;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dashboard.ver');
    }

    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'totais' => [
                'mesas_livres' => Mesa::livres()->count(),
                'pedidos_ativos' => Pedido::whereIn('estado', ['pendente', 'preparacao', 'pronto'])->count(),
                'socios_em_atraso' => Socio::emAtraso()->count(),
                'receita_dia' => (float) Cota::whereDate('data_pagamento', today())->where('estado', 'pago')->sum('valor'),
                'bar_hoje' => (float) Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])->whereDate('created_at', today())->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))->sum('total'),
            ],
        ]);
    }
}
