<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Evento;
use App\Models\Impressora;
use App\Models\PrintJob;
use App\Models\Produto;
use App\Models\TalaoConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TalaoConfigController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Talao/Edit', [
            'modelos' => TalaoConfig::with('evento:id,titulo')
                ->orderByDesc('em_uso')
                ->orderBy('nome')
                ->get(),
            'eventos' => Evento::orderByDesc('data_inicio')
                ->orderBy('titulo')
                ->get(['id', 'titulo', 'data_inicio']),
            'categorias' => Categoria::with(['produtos' => fn ($query) => $query->orderBy('nome')])
                ->orderBy('secao')
                ->orderBy('nome')
                ->get(),
            'impressoras' => Impressora::where('ativa', true)->orderBy('nome')->get(['id', 'nome', 'secao']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'evento_id' => ['nullable', 'exists:eventos,id'],
        ]);

        $evento = $dados['evento_id'] ? Evento::find($dados['evento_id']) : null;

        $modelo = TalaoConfig::create([
            'nome' => $dados['nome'],
            'evento_id' => $dados['evento_id'] ?? null,
            'titulo' => $evento ? mb_strtoupper($evento->titulo, 'UTF-8') : $dados['nome'],
            'cabecalho' => $evento ? $this->cabecalhoDoEvento($evento) : null,
            'rodape' => TalaoConfig::RODAPE_PADRAO,
            'instrucoes_individual' => "Entregar este talao no balcao",
            'ativo' => true,
            'em_uso' => TalaoConfig::count() === 0,
        ]);

        TalaoConfig::esquecer();

        return back()->with('success', 'Modelo "'.$modelo->nome.'" criado.');
    }

    public function update(Request $request, TalaoConfig $talao): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:80'],
            'evento_id' => ['nullable', 'exists:eventos,id'],
            'titulo' => ['required', 'string', 'max:60'],
            'cabecalho' => ['nullable', 'string', 'max:500'],
            'rodape' => ['nullable', 'string', 'max:500'],
            'instrucoes_individual' => ['nullable', 'string', 'max:500'],
            'rodape_em_pedidos' => ['nullable', 'boolean'],
            'prepago_apenas_individuais' => ['nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $talao->update($dados);

        TalaoConfig::esquecer();

        return back()->with('success', 'Modelo atualizado.');
    }

    public function usar(TalaoConfig $talao): RedirectResponse
    {
        $talao->marcarEmUso();

        return back()->with('success', 'Os talões passam a sair com "'.$talao->nome.'".');
    }

    public function destroy(TalaoConfig $talao): RedirectResponse
    {
        if ($talao->em_uso) {
            return back()->withErrors(['modelo' => 'Nao podes apagar o modelo que esta em uso. Escolhe outro primeiro.']);
        }

        $talao->delete();
        TalaoConfig::esquecer();

        return back()->with('success', 'Modelo apagado.');
    }

    /**
     * Que produtos saem em talao individual por unidade, para entregar ao cliente.
     * Antes estava fixo na seccao "frango".
     */
    public function produtos(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'produtos' => ['present', 'array'],
            'produtos.*' => ['integer', 'exists:produtos,id'],
        ]);

        $ids = $dados['produtos'];

        Produto::whereIn('id', $ids)->update(['talao_individual' => true]);
        Produto::whereNotIn('id', $ids ?: [0])->update(['talao_individual' => false]);

        return back()->with('success', 'Talões individuais atualizados.');
    }

    /**
     * Imprime um exemplo de cada tipo de talao, para conferir antes do evento.
     */
    public function teste(Request $request, TalaoConfig $talao): RedirectResponse
    {
        $request->validate([
            'impressora_id' => ['nullable', 'exists:impressoras,id'],
        ]);

        $impressora = $request->impressora_id
            ? Impressora::find($request->impressora_id)
            : Impressora::where('ativa', true)->orderBy('id')->first();

        if (! $impressora) {
            return back()->withErrors(['impressora_id' => 'Nao ha nenhuma impressora ativa.']);
        }

        // Conta
        PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => TalaoConfig::class,
            'printable_id' => $talao->id,
            'tipo' => 'teste',
            'payload' => [
                'titulo' => $talao->tituloImpresso(),
                'subtitulo' => 'TESTE / CONTA',
                'linhas' => [
                    ...$talao->linhasCabecalho(),
                    '------------------------------',
                    '1x Frango assado  10,00 EUR',
                    '------------------------------',
                    'Total: 10,00 EUR',
                    '',
                    ...$talao->linhasRodape(),
                ],
                'cortar' => true,
            ],
        ]);

        // Talao individual entregue ao cliente
        PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => TalaoConfig::class,
            'printable_id' => $talao->id,
            'tipo' => 'teste',
            'payload' => [
                'titulo' => $talao->tituloImpresso(),
                'subtitulo' => 'TESTE / SENHA',
                'linhas' => [
                    ...$talao->linhasCabecalho(),
                    'Ponto: Bar',
                    'Hora: '.now()->format('H:i'),
                    ['texto' => 'SENHA #99', 'alinhamento' => 'centro', 'tamanho' => 'grande'],
                    '------------------------------',
                    ['texto' => '1x Imperial', 'alinhamento' => 'centro', 'tamanho' => 'grande'],
                    ['texto' => 'BEBIDAS', 'alinhamento' => 'centro', 'tamanho' => 'grande'],
                    ['texto' => 'Talao 1 de 2', 'alinhamento' => 'centro'],
                    '------------------------------',
                    ...$talao->linhasInstrucoes(),
                ],
                'cortar' => true,
            ],
        ]);

        return back()->with('success', 'Dois talões de teste enviados para '.$impressora->nome.'.');
    }

    private function cabecalhoDoEvento(Evento $evento): string
    {
        return collect([
            $evento->subtitulo,
            $evento->data_inicio ? $this->datasDoEvento($evento) : null,
            $evento->localizacao,
        ])->filter()->implode("\n");
    }

    private function datasDoEvento(Evento $evento): string
    {
        $inicio = $evento->data_inicio;
        $fim = $evento->data_fim;

        if (! $fim || $inicio->isSameDay($fim)) {
            return $inicio->translatedFormat('j \d\e F \d\e Y');
        }

        if ($inicio->isSameMonth($fim)) {
            return $inicio->format('j').' a '.$fim->translatedFormat('j \d\e F \d\e Y');
        }

        return $inicio->translatedFormat('j \d\e F').' a '.$fim->translatedFormat('j \d\e F \d\e Y');
    }
}
