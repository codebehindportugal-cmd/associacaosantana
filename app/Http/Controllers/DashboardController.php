<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Socio;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totals = [
            'socios' => Socio::count(),
            'cotas' => Cota::count(),
            'cotas_em_atraso' => Cota::where('estado', 'em_atraso')->count(),
            'mesas' => Mesa::count(),
            'reservas' => Reserva::count(),
            'pedidos' => Pedido::count(),
            'valor_total_pedidos' => Pedido::sum('total'),
        ];

        return view('dashboard.index', compact('totals'));
    }
}
