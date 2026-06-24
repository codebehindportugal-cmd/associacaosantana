<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Models\SponsorImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SponsorImageController extends Controller
{
    public function store(Request $request, Sponsor $patrocinadore): RedirectResponse
    {
        $request->validate([
            'imagem' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:8192'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);

        $destino = public_path('images/sponsors');
        File::ensureDirectoryExists($destino);

        $ficheiro = $request->file('imagem');
        $nome = Str::uuid().'.'.$ficheiro->getClientOriginalExtension();
        $ficheiro->move($destino, $nome);

        $patrocinadore->images()->create([
            'path' => "/images/sponsors/{$nome}",
            'ordem' => $request->integer('ordem', 0),
        ]);

        return back()->with('success', 'Imagem adicionada.');
    }

    public function destroy(SponsorImage $imagem): RedirectResponse
    {
        if (str_starts_with($imagem->path, '/images/sponsors/')) {
            File::delete(public_path(ltrim($imagem->path, '/')));
        }

        $imagem->delete();

        return back()->with('success', 'Imagem removida.');
    }
}
