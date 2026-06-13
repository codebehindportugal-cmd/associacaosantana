<?php

namespace App\Http\Controllers;

use App\Models\ZonaMapa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZonaMapaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        ZonaMapa::create([
            ...$validated,
            'mapa_x' => (int) ($validated['mapa_x'] ?? 45),
            'mapa_y' => (int) ($validated['mapa_y'] ?? 45),
            'mapa_largura' => (int) ($validated['mapa_largura'] ?? 10),
            'mapa_altura' => (int) ($validated['mapa_altura'] ?? 8),
        ]);

        return back()->with('success', 'Elemento criado.');
    }

    public function update(Request $request, ZonaMapa $zona): RedirectResponse
    {
        $zona->update($this->validated($request, $zona));

        return back()->with('success', 'Elemento atualizado.');
    }

    public function destroy(ZonaMapa $zona): RedirectResponse
    {
        $zona->delete();

        return back()->with('success', 'Elemento apagado.');
    }

    public function guardarMapa(Request $request): RedirectResponse
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

    private function validated(Request $request, ?ZonaMapa $zona = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:80', 'unique:zona_mapas,nome,'.($zona?->id ?? 'NULL')],
            'tipo' => ['required', 'string', 'max:30'],
            'mapa_x' => ['nullable', 'integer', 'between:0,100'],
            'mapa_y' => ['nullable', 'integer', 'between:0,100'],
            'mapa_largura' => ['nullable', 'integer', 'between:1,60'],
            'mapa_altura' => ['nullable', 'integer', 'between:1,60'],
        ]);
    }
}

