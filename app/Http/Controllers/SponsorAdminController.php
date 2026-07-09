<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Models\SponsorImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SponsorAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Patrocinadores/Index', [
            'patrocinadores' => Schema::hasTable('sponsors')
                ? Sponsor::with('images')->orderBy('ordem')->orderBy('empresa')->get()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['mostrar_no_slider'] = $request->boolean('mostrar_no_slider');
        $data['ativo'] = $request->boolean('ativo');
        $data['logotipo'] = $this->guardarLogo($request);

        Sponsor::create($data);

        return back()->with('success', 'Patrocinador criado.');
    }

    public function update(Request $request, Sponsor $patrocinadore): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['mostrar_no_slider'] = $request->boolean('mostrar_no_slider');
        $data['ativo'] = $request->boolean('ativo');

        if ($logo = $this->guardarLogo($request)) {
            $this->apagarLogo($patrocinadore->logotipo);
            $data['logotipo'] = $logo;
        }

        $patrocinadore->update($data);

        return back()->with('success', 'Patrocinador atualizado.');
    }

    public function destroy(Sponsor $patrocinadore): RedirectResponse
    {
        foreach ($patrocinadore->images as $imagem) {
            $this->apagarLogo($imagem->path);
        }
        $patrocinadore->images()->delete();
        $this->apagarLogo($patrocinadore->logotipo);
        $patrocinadore->delete();

        return back()->with('success', 'Patrocinador apagado.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'empresa' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:2048'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'mostrar_no_slider' => ['nullable'],
            'ativo' => ['nullable'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'logotipo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);
    }

    private function guardarLogo(Request $request): ?string
    {
        if (! $request->hasFile('logotipo')) {
            return null;
        }

        $destino = public_path('images/sponsors');
        File::ensureDirectoryExists($destino);

        $ficheiro = $request->file('logotipo');
        $nome = Str::uuid().'.'.$ficheiro->getClientOriginalExtension();
        $ficheiro->move($destino, $nome);

        return "/images/sponsors/{$nome}";
    }

    private function apagarLogo(?string $caminho): void
    {
        if (! $caminho || ! str_starts_with($caminho, '/images/sponsors/')) {
            return;
        }

        File::delete(public_path(ltrim($caminho, '/')));
    }
}
