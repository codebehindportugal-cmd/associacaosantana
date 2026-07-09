<?php

namespace App\Http\Controllers;

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
                'pedidos_fechados_hoje' => Pedido::whereDate('updated_at', today())->where('estado', 'entregue')->count(),
                'pedidos_bar_hoje' => Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
