<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\EventoInscricao;
use App\Models\EventoMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventoController extends Controller
{
    public function publicShow(Evento $evento): Response
    {
        abort_unless($evento->estado === 'publicado', 404);

        return Inertia::render('Eventos/PublicShow', [
            'evento' => $this->eventoData($evento->load('media')),
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('Eventos/Index', [
            'eventos' => Evento::with('media')
                ->orderByDesc('data_inicio')
                ->orderBy('ordem')
                ->get()
                ->map(fn (Evento $evento) => $this->eventoData($evento)),
        ]);
    }

    public function show(Evento $evento): Response
    {
        return Inertia::render('Eventos/Show', [
            'evento' => $this->eventoData($evento->load('media')),
        ]);
    }

    public function edit(Evento $evento): Response
    {
        return Inertia::render('Eventos/Edit', [
            'evento' => $this->eventoData($evento->load('media')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['programa_texto'], $data['cartaz']);
        $data['ordem'] ??= 0;
        $data['destaque'] ??= false;
        $data['cartaz'] = $this->guardarFicheiro($request, 'cartaz') ?? null;
        $data['programa'] = $this->programaFromText($request->string('programa_texto')->toString(), $data);
        $data = $this->aplicarInscricoes($data);

        Evento::create($data);

        return back()->with('success', 'Evento criado.');
    }

    public function update(Request $request, Evento $evento): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['programa_texto'], $data['cartaz']);
        $data['ordem'] ??= 0;
        $data['destaque'] ??= false;
        $data['programa'] = $this->programaFromText($request->string('programa_texto')->toString(), $data);
        $data = $this->aplicarInscricoes($data);

        if ($cartaz = $this->guardarFicheiro($request, 'cartaz')) {
            $this->apagarFicheiroPublico($evento->cartaz);
            $data['cartaz'] = $cartaz;
        }

        $evento->update($data);

        return back()->with('success', 'Evento atualizado.');
    }

    public function destroy(Evento $evento): RedirectResponse
    {
        $this->apagarFicheiroPublico($evento->cartaz);
        $evento->media->each(fn (EventoMedia $media) => $this->apagarFicheiroPublico($media->caminho));
        $evento->delete();

        return back()->with('success', 'Evento apagado.');
    }

    public function storeMedia(Request $request, Evento $evento): RedirectResponse
    {
        $request->validate([
            'ficheiros' => ['required', 'array'],
            'ficheiros.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
            'origem' => ['nullable', 'in:evento,redes_sociais'],
        ]);

        $ordemInicial = $evento->media()->count();
        $origem = $request->input('origem', 'evento');

        foreach ($request->file('ficheiros', []) as $index => $ficheiro) {
            $evento->media()->create([
                'tipo' => str_starts_with((string) $ficheiro->getMimeType(), 'video/') ? 'video' : 'foto',
                'caminho' => $this->moverUpload($ficheiro, 'events/uploads'),
                'titulo' => pathinfo($ficheiro->getClientOriginalName(), PATHINFO_FILENAME),
                'origem' => $origem,
                'ordem' => $ordemInicial + $index,
            ]);
        }

        return back()->with('success', 'Ficheiros adicionados ao evento.');
    }

    public function storeMediaUrl(Request $request, Evento $evento): RedirectResponse
    {
        $data = $request->validate([
            'imagem_url' => ['required', 'url', 'max:2048'],
            'titulo' => ['nullable', 'string', 'max:255'],
        ]);

        $evento->media()->create([
            'tipo' => 'foto',
            'caminho' => $data['imagem_url'],
            'titulo' => $data['titulo'] ?: 'Imagem das redes sociais',
            'origem' => 'redes_sociais',
            'url_origem' => $data['imagem_url'],
            'ordem' => $evento->media()->count(),
        ]);

        return back()->with('success', 'Imagem das redes sociais adicionada ao evento.');
    }

    public function destroyMedia(EventoMedia $media): RedirectResponse
    {
        $this->apagarFicheiroPublico($media->caminho);
        $media->delete();

        return back()->with('success', 'Ficheiro removido.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'periodo' => ['nullable', 'string', 'max:255'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'cartaz' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'facebook_post_url' => ['nullable', 'url', 'max:2048'],
            'estado' => ['required', 'in:rascunho,publicado'],
            'destaque' => ['boolean'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'programa_texto' => ['nullable', 'string'],
            'inscricoes_ativas' => ['boolean'],
            'inscricoes_limite' => ['nullable', 'integer', 'min:1'],
            'inscricoes_opcoes_texto' => ['nullable', 'string'],
            'inscricoes_pede_idades' => ['boolean'],
            'inscricoes_preco' => ['nullable', 'numeric', 'min:0'],
            'inscricoes_preco_crianca' => ['nullable', 'numeric', 'min:0'],
            'inscricoes_idade_crianca' => ['nullable', 'integer', 'min:1', 'max:17'],
            'inscricoes_pagamento_online' => ['boolean'],
        ]);
    }

    /**
     * Gestão das inscrições de um evento no back-office.
     */
    public function inscricoes(Evento $evento): Response
    {
        return Inertia::render('Eventos/Inscricoes', [
            'evento' => [
                'id' => $evento->id,
                'titulo' => $evento->titulo,
                'inscricoes_ativas' => (bool) $evento->inscricoes_ativas,
                'inscricoes_limite' => $evento->inscricoes_limite,
                'pede_idades' => (bool) $evento->inscricoes_pede_idades,
                'tem_opcoes' => ! empty($evento->inscricoes_opcoes),
            ],
            'inscricoes' => $evento->inscricoes()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (EventoInscricao $inscricao) => [
                    'id' => $inscricao->id,
                    'nome' => $inscricao->nome,
                    'telefone' => $inscricao->telefone,
                    'email' => $inscricao->email,
                    'pagamento_estado' => $inscricao->pagamento_estado,
                    'num_pessoas' => $inscricao->num_pessoas,
                    'opcao' => $inscricao->opcao,
                    'num_criancas' => $inscricao->num_criancas,
                    'idades_criancas' => $inscricao->idades_criancas,
                    'observacoes' => $inscricao->observacoes,
                    'valor_estimado' => $inscricao->valor_estimado,
                    'criado_em' => $inscricao->created_at->format('d/m/Y H:i'),
                ]),
            'totais' => [
                'inscricoes' => $evento->inscricoes()->count(),
                'pessoas' => $evento->totalPessoasInscritas(),
                'valor' => (float) $evento->inscricoes()->sum('valor_estimado'),
            ],
            'urlPublica' => route('inscricoes.index'),
        ]);
    }

    public function destroyInscricao(EventoInscricao $inscricao): RedirectResponse
    {
        $inscricao->delete();

        return back()->with('success', 'Inscrição removida.');
    }

    /**
     * Converte o texto das opções (uma por linha) em array e normaliza defaults.
     */
    private function aplicarInscricoes(array $data): array
    {
        $texto = $data['inscricoes_opcoes_texto'] ?? '';
        unset($data['inscricoes_opcoes_texto']);

        $data['inscricoes_ativas'] ??= false;
        $data['inscricoes_pede_idades'] ??= false;
        $data['inscricoes_pagamento_online'] ??= false;
        // Cada linha: "Nome da opção" ou "Nome da opção = 12.50"
        $data['inscricoes_opcoes'] = collect(preg_split('/\r\n|\r|\n/', (string) $texto))
            ->map(fn ($linha) => trim($linha))
            ->filter()
            ->map(function ($linha) {
                if (preg_match('/^(.+?)\s*=\s*([\d]+(?:[.,]\d{1,2})?)\s*€?\s*$/u', $linha, $m)) {
                    return ['nome' => trim($m[1]), 'preco' => (float) str_replace(',', '.', $m[2])];
                }

                return ['nome' => $linha, 'preco' => null];
            })
            ->values()
            ->all() ?: null;

        return $data;
    }

    private function eventoData(Evento $evento): array
    {
        return [
            'id' => $evento->id,
            'titulo' => $evento->titulo,
            'subtitulo' => $evento->subtitulo,
            'data_inicio' => optional($evento->data_inicio)->toDateString(),
            'data_fim' => optional($evento->data_fim)->toDateString(),
            'periodo' => $evento->periodo,
            'localizacao' => $evento->localizacao,
            'badge' => $evento->badge,
            'descricao' => $evento->descricao,
            'cartaz' => $evento->cartaz,
            'facebook_post_url' => $evento->facebook_post_url,
            'programa' => $evento->programa ?? [],
            'estado' => $evento->estado,
            'destaque' => $evento->destaque,
            'ordem' => $evento->ordem,
            'inscricoes_ativas' => (bool) $evento->inscricoes_ativas,
            'inscricoes_limite' => $evento->inscricoes_limite,
            'inscricoes_opcoes' => $evento->opcoesInscricao(),
            'inscricoes_pede_idades' => (bool) $evento->inscricoes_pede_idades,
            'inscricoes_preco' => $evento->inscricoes_preco,
            'inscricoes_preco_crianca' => $evento->inscricoes_preco_crianca,
            'inscricoes_idade_crianca' => $evento->inscricoes_idade_crianca,
            'inscricoes_pagamento_online' => (bool) $evento->inscricoes_pagamento_online,
            'inscricoes_total' => $evento->inscricoes()->count(),
            'pessoas_inscritas' => $evento->totalPessoasInscritas(),
            'created_at' => optional($evento->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($evento->updated_at)->format('d/m/Y H:i'),
            'media' => $evento->media->map(fn (EventoMedia $media) => [
                'id' => $media->id,
                'tipo' => $media->tipo,
                'caminho' => $media->caminho,
                'titulo' => $media->titulo,
                'origem' => $media->origem,
                'url_origem' => $media->url_origem,
            ])->values(),
        ];
    }

    private function programaFromText(string $texto, array $data): array
    {
        $items = collect(preg_split('/\R/u', $texto))
            ->map(fn ($linha) => trim((string) $linha))
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return [];
        }

        return [[
            'day' => $data['data_inicio'] ?? ($data['periodo'] ?? 'Programa'),
            'label' => $data['badge'] ?? 'Programa',
            'items' => $items,
        ]];
    }

    private function guardarFicheiro(Request $request, string $campo): ?string
    {
        if (! $request->hasFile($campo)) {
            return null;
        }

        return $this->moverUpload($request->file($campo), 'events/uploads');
    }

    private function moverUpload($ficheiro, string $pasta): string
    {
        $destino = public_path("images/{$pasta}");
        File::ensureDirectoryExists($destino);

        $nome = Str::uuid().'.'.$ficheiro->getClientOriginalExtension();
        $ficheiro->move($destino, $nome);

        return "/images/{$pasta}/{$nome}";
    }

    private function apagarFicheiroPublico(?string $caminho): void
    {
        if (! $caminho || ! str_starts_with($caminho, '/images/events/uploads/')) {
            return;
        }

        File::delete(public_path(ltrim($caminho, '/')));
    }
}
