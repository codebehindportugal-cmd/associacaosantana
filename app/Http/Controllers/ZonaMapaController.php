<?php

namespace App\Http\Controllers;

use App\Models\ZonaMapa;
use Illuminate\Http\Request;

class ZonaMapaController extends Controller
{
    public function guardarMapa(Request $request)
    {
        $validated = $request->validate([
            'zonas' => 'required|array',
            'zonas.*.id' => 'required|exists:zona_mapas',
            'zonas.*.mapa_x' => 'required|integer|min:0|max:100',
            'zonas.*.mapa_y' => 'required|integer|min:0|max:100',
            'zonas.*.mapa_largura' => 'required|integer|min:1|max:100',
            'zonas.*.mapa_altura' => 'required|integer|min:1|max:100',
        ]);

        foreach ($validated['zonas'] as $zonaData) {
            ZonaMapa::where('id', $zonaData['id'])->update([
                'mapa_x' => $zonaData['mapa_x'],
                'mapa_y' => $zonaData['mapa_y'],
                'mapa_largura' => $zonaData['mapa_largura'],
                'mapa_altura' => $zonaData['mapa_altura'],
            ]);
        }

        return back()->with('success', 'Mapa atualizado com sucesso');
    }
}

