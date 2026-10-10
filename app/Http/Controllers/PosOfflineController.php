<?php

namespace App\Http\Controllers;

use App\Exceptions\EventoPorEnquanto;
use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\Produto;
use App\Models\TalaoConfig;
use App\Services\CaixaService;
use App\Services\PrintJobService;
use App\Support\Stock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POS do bar que trabalha sem internet.
 *
 * O posto guarda no computador os produtos, o modelo de talao e o ultimo
 * numero de senha (dados), vende e imprime localmente, e envia as vendas
 * (e anulacoes e devolucoes de caucao) quando houver rede (enviar).
 *
 * Regras para nao perder nem duplicar dinheiro:
 *  - cada evento traz um uuid: reenviar o mesmo evento nao o duplica;
 *  - cada evento diz de que posto, ponto, letra e caixa e: fica la, mesmo que
 *    o posto mude depois ou o envio seja feito com outra sessao;
 *  - as vendas nunca sao recusadas por contas ou stock (o dinheiro ja mudou
 *    de maos): o que nao bate fica assinalado nas observacoes;
 *  - so dados impossiveis sao recusados de vez; erros passageiros (base de
 *    dados ocupada, etc.) ficam na fila do posto e voltam a ser enviados.
 */
class PosOfflineController extends Controller
{
    /** Quantos eventos aceitar num envio (o posto manda em lotes). */
    private const MAX_EVENTOS = 100;

    /** Diferenca entre o relogio do posto e o do servidor, neste envio. */
    private int $desvioSegundos = 0;

    public function __construct(private CaixaService $caixas)
    {
    }

    public function dados(): JsonResponse
    {
        $posto = $this->posto();
        $ponto = $this->ponto();
        $caixa = CaixaDiaria::abertaParaPonto($ponto);
        $talao = TalaoConfig::atual();

        return response()->json([
            'agora' => now()->toIso8601String(),
            'posto' => [
                'id' => $posto->id,
                'nome' => $posto->nome,
                'ponto' => $ponto,
                'offline' => (bool) $posto->offline,
                'prefixo' => $posto->prefixo_senha,
                'impressora' => $posto->impressora?->tipo,
            ],
            'caixa' => $caixa ? ['id' => $caixa->id, 'aberta_em' => $caixa->created_at->toIso8601String()] : null,
            // Ultimo numero ja recebido nesta caixa, para o posto nunca repetir mesmo que perca os dados
            'ultimo_numero' => $caixa ? (int) Pedido::where('ponto_bar', $ponto)
                ->where('prefixo_senha', $posto->prefixo_senha)
                ->where('created_at', '>=', $caixa->created_at)
                ->max('numero_senha') : 0,
            'produtos' => Produto::with('categoria')
                ->where('disponivel', true)
                ->where('disponivel_bar', true)
                ->orderBy('nome')
                ->get()
                ->map(fn (Produto $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'preco' => (float) $p->preco,
                    'caucao' => (float) $p->caucao,
                    'imagem' => $p->imagem,
                    'gerir_stock' => (bool) $p->gerir_stock,
                    'stock_atual' => (float) $p->stock_atual,
                    'talao_individual' => (bool) $p->talao_individual,
                    'categoria' => ['nome' => $p->categoria?->nome, 'secao' => $p->categoria?->secao],
                ])->values(),
            'talao' => [
                'titulo' => $talao->tituloImpresso(),
                'cabecalho' => $talao->linhasCabecalho(),
                'rodape' => $talao->linhasRodape(),
                'instrucoes' => $talao->linhasInstrucoes(),
                'por_seccao' => $talao->taloesPorSeccao(),
            ],
            'metodos' => Pedido::METODOS_PREPAGO,
        ]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $request->validate([
            'eventos' => ['required', 'array', 'max:'.self::MAX_EVENTOS],
            'eventos.*.tipo' => ['required', Rule::in(['venda', 'anulacao', 'caucao'])],
            'eventos.*.uuid' => ['required', 'uuid'],
            'enviado_em' => ['nullable', 'date'],
        ]);

        // Relogio do posto errado (computador sem acerto de hora): corrige pela diferenca
        if ($request->filled('enviado_em')) {
            $this->desvioSegundos = (int) Carbon::parse($request->input('enviado_em'))->diffInSeconds(now(), false);
        }

        $postoSessao = $this->posto();
        $resultados = [];

        // Pela ordem em que aconteceram: uma anulacao vem sempre depois da venda
        foreach ($request->input('eventos') as $evento) {
            $uuid = $evento['uuid'];

            try {
                // Registo feito noutro posto (ex.: outro posto entrou neste computador): fica a espera
                if ((int) ($evento['pos_id'] ?? 0) !== $postoSessao->id) {
                    throw new EventoPorEnquanto('Este registo e de outro posto. Entra nesse posto para o enviar.');
                }

                $resultados[] = ['uuid' => $uuid, 'ok' => true] + match ($evento['tipo']) {
                    'venda' => $this->registarVenda($evento, $postoSessao),
                    'anulacao' => $this->registarAnulacao($evento, $postoSessao),
                    'caucao' => $this->registarCaucao($evento, $postoSessao),
                };
            } catch (UniqueConstraintViolationException) {
                // O mesmo registo chegou duas vezes ao mesmo tempo: o outro pedido ja o guardou
                $id = $evento['tipo'] === 'caucao'
                    ? CaucaoDevolucao::where('uuid', $uuid)->value('id')
                    : Pedido::where('uuid', $uuid)->value('id');
                $resultados[] = $id
                    ? ['uuid' => $uuid, 'ok' => true, 'id' => $id]
                    : ['uuid' => $uuid, 'ok' => false, 'definitivo' => false, 'erro' => 'Registo repetido, a confirmar.'];
            } catch (ValidationException $e) {
                // Dados impossiveis: nunca vao passar. Sai da fila mas fica registado no posto e aqui.
                Log::warning('POS offline: registo recusado', ['uuid' => $uuid, 'posto' => $postoSessao->id, 'erros' => $e->errors(), 'evento' => $evento]);
                $resultados[] = ['uuid' => $uuid, 'ok' => false, 'definitivo' => true, 'erro' => collect($e->errors())->flatten()->first() ?? 'Dados invalidos.'];
            } catch (EventoPorEnquanto $e) {
                $resultados[] = ['uuid' => $uuid, 'ok' => false, 'definitivo' => false, 'erro' => $e->getMessage()];
            } catch (\Throwable $e) {
                // Base de dados ocupada, ligacao em baixo, etc.: volta a ser enviado
                Log::warning('POS offline: erro a registar, fica para o proximo envio', ['uuid' => $uuid, 'erro' => $e->getMessage()]);
                $resultados[] = ['uuid' => $uuid, 'ok' => false, 'definitivo' => false, 'erro' => 'Erro no servidor; volta a tentar sozinho.'];
            }
        }

        return response()->json(['resultados' => $resultados]);
    }

    private function registarVenda(array $e, PosSession $posto): array
    {
        $existente = Pedido::where('uuid', $e['uuid'])->first();
        if ($existente) {
            return ['id' => $existente->id];
        }

        validator($e, [
            'numero' => ['required', 'integer', 'min:1'],
            'criado_em' => ['required', 'date'],
            'ponto' => ['required', 'string', 'max:255'],
            'prefixo' => ['nullable', 'string', 'max:3'],
            'caixa_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', 'integer', 'exists:produtos,id'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.preco' => ['required', 'numeric', 'min:0'],
            'devolvidos' => ['nullable', 'array'],
            'devolvidos.*.produto_id' => ['required', 'integer', 'exists:produtos,id'],
            'devolvidos.*.quantidade' => ['required', 'integer', 'min:1'],
            'devolvidos.*.caucao' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'caucao_cobrada' => ['required', 'numeric', 'min:0'],
            'caucao_descontada' => ['required', 'numeric', 'min:0'],
            'valor_recebido' => ['required', 'numeric', 'min:0'],
            'troco' => ['required', 'numeric', 'min:0'],
            'doacao' => ['required', 'numeric', 'min:0'],
            'metodo' => ['required', Rule::in(array_keys(Pedido::METODOS_PREPAGO))],
            'juntar' => ['nullable', 'array'],
            'operador' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $ponto = $e['ponto'];
        $prefixo = $e['prefixo'] ?? $posto->prefixo_senha;
        $avisos = [];
        [$caixa, $quando] = $this->caixaEHora($e, $ponto, $avisos);

        // Contas que nao batem certo com os artigos: guarda-se o que o posto cobrou e assinala-se
        $somaArtigos = round(collect($e['items'])->sum(fn ($i) => (float) $i['preco'] * (int) $i['quantidade']), 2);
        if (abs($somaArtigos - (float) $e['total']) > 0.01) {
            $avisos[] = 'Total do posto ('.$e['total'].') diferente da soma dos artigos ('.$somaArtigos.')';
        }
        $somaDevolvidos = round(collect($e['devolvidos'] ?? [])->sum(fn ($d) => (float) $d['caucao'] * (int) $d['quantidade']), 2);
        if (abs($somaDevolvidos - (float) $e['caucao_descontada']) > 0.01) {
            $avisos[] = 'Caucao descontada ('.$e['caucao_descontada'].') diferente das caucoes devolvidas ('.$somaDevolvidos.')';
        }
        // Dois computadores no mesmo posto davam o mesmo numero: aceita-se, mas fica assinalado
        $repetido = Pedido::where('ponto_bar', $ponto)
            ->where('prefixo_senha', $prefixo)
            ->where('numero_senha', (int) $e['numero'])
            ->when($caixa, fn ($q) => $q->where('created_at', '>=', $caixa->created_at))
            ->exists();
        if ($repetido) {
            $avisos[] = 'Numero de senha repetido neste ponto';
        }

        return DB::transaction(function () use ($e, $posto, $ponto, $prefixo, $caixa, $quando, $avisos) {
            $pedido = new Pedido([
                'uuid' => $e['uuid'],
                'pos_id' => $posto->id,
                'operador_nome' => ($e['operador'] ?? null) ?: $posto->nome,
                'tipo' => 'bar_prepago',
                'estado' => 'pronto',
                'numero_senha' => (int) $e['numero'],
                'prefixo_senha' => $prefixo,
                'pago_antecipado' => true,
                'ponto_bar' => $ponto,
                'total' => round((float) $e['total'], 2),
                'caucao_cobrada' => round((float) $e['caucao_cobrada'], 2),
                'caucao_descontada' => round((float) $e['caucao_descontada'], 2),
                'valor_recebido' => round((float) $e['valor_recebido'], 2),
                'troco' => round((float) $e['troco'], 2),
                'doacao' => round((float) $e['doacao'], 2),
                'metodo_pagamento' => $e['metodo'],
                'juntar' => array_map('boolval', array_intersect_key($e['juntar'] ?? [], PrintJobService::GRUPOS_JUNTOS)) ?: null,
                'observacoes' => $avisos ? 'Sem internet: '.implode('; ', $avisos).'.' : null,
            ]);
            $pedido->created_at = $quando;
            $pedido->updated_at = $quando;
            $pedido->save();

            $produtos = Produto::with('categoria')->whereIn('id', collect($e['items'])->pluck('produto_id'))->get()->keyBy('id');

            Stock::semLimite(function () use ($e, $pedido, $produtos, $quando) {
                foreach ($e['items'] as $item) {
                    $linha = $pedido->items()->make([
                        'produto_id' => (int) $item['produto_id'],
                        'quantidade' => (int) $item['quantidade'],
                        'preco_unitario' => round((float) $item['preco'], 2),
                        'secao' => $produtos[$item['produto_id']]->categoria?->secao,
                    ]);
                    $linha->created_at = $quando;
                    $linha->updated_at = $quando;
                    $linha->save();
                }
            });

            foreach ($e['devolvidos'] ?? [] as $d) {
                $devolucao = new CaucaoDevolucao([
                    'produto_id' => (int) $d['produto_id'],
                    'pedido_id' => $pedido->id,
                    'pos_id' => $posto->id,
                    'operador_nome' => $pedido->operador_nome,
                    'ponto' => $ponto,
                    'modo' => 'bebidas',
                    'quantidade' => (int) $d['quantidade'],
                    'valor_unitario' => round((float) $d['caucao'], 2),
                    'valor_total' => round((float) $d['caucao'] * (int) $d['quantidade'], 2),
                ]);
                $devolucao->created_at = $quando;
                $devolucao->updated_at = $quando;
                $devolucao->save();
            }

            if ($avisos) {
                Log::warning('POS offline: venda com avisos', ['pedido' => $pedido->id, 'avisos' => $avisos]);
            }

            $this->acertarCaixaFechada($caixa);

            return ['id' => $pedido->id];
        });
    }

    private function registarAnulacao(array $e, PosSession $posto): array
    {
        validator($e, [
            'venda_uuid' => ['required', 'uuid'],
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
            'criado_em' => ['required', 'date'],
            'operador' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $pedido = Pedido::where('uuid', $e['venda_uuid'])->first();
        if (! $pedido) {
            throw new EventoPorEnquanto('A venda desta anulacao ainda nao chegou ao servidor.');
        }

        // So o proprio posto anula sem internet as suas senhas
        if ((int) $pedido->pos_id !== $posto->id) {
            throw ValidationException::withMessages(['venda_uuid' => 'Esta senha nao foi vendida neste posto.']);
        }

        return DB::transaction(function () use ($pedido, $e) {
            $pedido = Pedido::lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->estado === 'cancelado') {
                return ['id' => $pedido->id];
            }

            $pago = round((float) $pedido->total + (float) $pedido->caucao_cobrada - (float) $pedido->caucao_descontada + (float) $pedido->doacao, 2);
            $metros = round((float) $pedido->caucao_descontada, 2);

            CaucaoDevolucao::where('pedido_id', $pedido->id)->where('modo', 'bebidas')->update(['modo' => 'dinheiro']);

            $pedido->update([
                'estado' => 'cancelado',
                'pago_antecipado' => false,
                'anulado_em' => $this->hora($e['criado_em'])->max($pedido->created_at),
                'anulado_por' => ($e['operador'] ?? null) ?: (session('pos_operador') ?: session('pos_nome')),
                'motivo_anulacao' => $e['motivo'].' (sem internet)',
                'valor_devolvido' => $pago + $metros,
            ]);

            $this->acertarCaixaFechada(CaixaDiaria::where('ponto', $pedido->ponto_bar)
                ->where('created_at', '<=', $pedido->created_at)
                ->latest('created_at')->latest('id')->first());

            return ['id' => $pedido->id];
        });
    }

    private function registarCaucao(array $e, PosSession $posto): array
    {
        $existente = CaucaoDevolucao::where('uuid', $e['uuid'])->first();
        if ($existente) {
            return ['id' => $existente->id];
        }

        validator($e, [
            'produto_id' => ['required', 'integer', 'exists:produtos,id'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:50'],
            'caucao' => ['required', 'numeric', 'min:0'],
            'criado_em' => ['required', 'date'],
            'ponto' => ['required', 'string', 'max:255'],
            'caixa_id' => ['nullable', 'integer'],
            'operador' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $avisos = [];
        [$caixa, $quando] = $this->caixaEHora($e, $e['ponto'], $avisos);
        if ($avisos) {
            Log::warning('POS offline: devolucao de caucao com avisos', ['uuid' => $e['uuid'], 'avisos' => $avisos]);
        }

        $devolucao = new CaucaoDevolucao([
            'uuid' => $e['uuid'],
            'produto_id' => (int) $e['produto_id'],
            'pos_id' => $posto->id,
            'operador_nome' => ($e['operador'] ?? null) ?: session('pos_operador'),
            'ponto' => $e['ponto'],
            'modo' => 'dinheiro',
            'quantidade' => (int) $e['quantidade'],
            'valor_unitario' => round((float) $e['caucao'], 2),
            'valor_total' => round((float) $e['caucao'] * (int) $e['quantidade'], 2),
        ]);
        $devolucao->created_at = $quando;
        $devolucao->updated_at = $quando;
        $devolucao->save();

        $this->acertarCaixaFechada($caixa);

        return ['id' => $devolucao->id];
    }

    /** A caixa em que o posto estava quando fez o registo (vem no evento). */
    private function caixaDoEvento(array $e, string $ponto): ?CaixaDiaria
    {
        if (! empty($e['caixa_id'])) {
            $caixa = CaixaDiaria::where('ponto', $ponto)->find($e['caixa_id']);
            if ($caixa) {
                return $caixa;
            }
        }

        return CaixaDiaria::where('ponto', $ponto)->where('created_at', '<=', $this->hora($e['criado_em']))
            ->latest('created_at')->latest('id')->first();
    }

    /** Hora do posto acertada pelo relogio do servidor. */
    private function hora(string $criadoEm): Carbon
    {
        return Carbon::parse($criadoEm)->setTimezone(config('app.timezone'))->addSeconds($this->desvioSegundos);
    }

    /**
     * Caixa e hora do registo. Normalmente a caixa em que o posto estava. Se
     * pela hora (acertada) o registo foi feito DEPOIS de essa caixa fechar
     * (o posto continuou a vender sem rede), conta na caixa que estava aberta
     * a essa hora; se nenhuma estava, fica fora de caixa e assinalado — nunca
     * entra numa caixa ja contada como dinheiro que la nao estava.
     *
     * @return array{0: ?CaixaDiaria, 1: Carbon}
     */
    private function caixaEHora(array $e, string $ponto, array &$avisos): array
    {
        $caixa = $this->caixaDoEvento($e, $ponto);
        $hora = $this->hora($e['criado_em']);
        if ($hora->gt(now())) {
            $hora = now();
        }

        if ($caixa?->fechado_at && $hora->gt($caixa->fechado_at)) {
            $aberta = CaixaDiaria::where('ponto', $ponto)
                ->where('created_at', '<=', $hora)
                ->where(fn ($q) => $q->whereNull('fechado_at')->orWhere('fechado_at', '>=', $hora))
                ->latest('created_at')->latest('id')->first();

            if (! $aberta) {
                $avisos[] = 'Registado depois do fecho da caixa, com nenhuma caixa aberta';

                return [null, $hora];
            }
            $caixa = $aberta;
        }

        return [$caixa, $this->quando($e['criado_em'], $caixa)];
    }

    /**
     * Hora a registar: a do posto (acertada), mas sempre dentro da caixa em
     * que o registo foi feito — entre a abertura e o fecho — para contar nas
     * contas dessa caixa e nao na seguinte.
     */
    private function quando(string $criadoEm, ?CaixaDiaria $caixa): Carbon
    {
        $quando = $this->hora($criadoEm);
        $limite = $caixa?->fechado_at ?? now();

        if ($quando->gt($limite)) {
            $quando = $limite->copy();
        }
        if ($caixa && $quando->lt($caixa->created_at)) {
            $quando = $caixa->created_at->copy();
        }

        return $quando;
    }

    /**
     * Registo que chegou depois de a caixa ter sido fechada (fechada no
     * backoffice com o posto ainda por enviar): a diferenca do fecho volta a
     * ser calculada com ele, e fica uma nota.
     */
    private function acertarCaixaFechada(?CaixaDiaria $caixa): void
    {
        if (! $caixa || $caixa->estado !== 'fechada' || $caixa->valor_contado === null) {
            return;
        }

        $caixa->refresh();
        $esperado = $this->caixas->esperadoNaGaveta($caixa, $this->caixas->vendasDoPonto($caixa), $this->caixas->caucoesDoPonto($caixa));
        $nota = 'Registos sem internet chegaram depois do fecho; diferenca recalculada.';

        $caixa->update([
            'diferenca' => round((float) $caixa->valor_contado - $esperado, 2),
            'observacoes_fecho' => str_contains((string) $caixa->observacoes_fecho, $nota)
                ? $caixa->observacoes_fecho
                : trim($caixa->observacoes_fecho."\n".$nota),
        ]);
    }

    private function posto(): PosSession
    {
        return PosSession::with('impressora')->findOrFail(session('pos_id'));
    }

    private function ponto(): string
    {
        return (string) (session('pos_localizacao') ?: session('pos_nome'));
    }
}
