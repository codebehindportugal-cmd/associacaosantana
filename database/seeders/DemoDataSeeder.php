<?php

namespace Database\Seeders;

use App\Models\CaixaDiaria;
use App\Models\Categoria;
use App\Models\Cota;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PosSession;
use App\Models\Produto;
use App\Models\Reserva;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    private const MARK = '[DEMO]';

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CategoriaSeeder::class,
            MesaSeeder::class,
            PosSessionSeeder::class,
        ]);

        DB::transaction(function () {
            $this->clearDemoData();

            $users = $this->users();
            $produtos = $this->produtos();
            $socios = $this->socios();

            $this->cotas($socios);
            $this->reservas();
            $this->pedidos($users, $produtos);
            $this->caixas($users);
        });
    }

    private function clearDemoData(): void
    {
        Pedido::where('observacoes', 'like', self::MARK.'%')->get()->each->delete();
        Cota::where('observacoes', 'like', self::MARK.'%')->delete();
        CaixaDiaria::where('observacoes_fecho', 'like', self::MARK.'%')->delete();
        Reserva::whereIn('nome', $this->reservaNomes())->delete();
        Socio::where('numero_socio', 'like', 'D9%')->delete();
    }

    private function users(): array
    {
        return [
            'gerente' => User::where('email', 'gerente@santana.pt')->firstOrFail(),
            'restaurante' => User::where('email', 'pedidos@santana.pt')->firstOrFail(),
            'tesoureiro' => User::where('email', 'tesoureiro@santana.pt')->firstOrFail(),
        ];
    }

    private function produtos(): array
    {
        $categorias = [
            'Bebidas' => 'bebidas',
            'Comida' => 'comida',
            'Frango' => 'frango',
            'Acompanhamentos' => 'acompanhamentos',
        ];

        foreach ($categorias as $nome => $secao) {
            Categoria::updateOrCreate(['nome' => $nome], ['secao' => $secao]);
        }

        $produtos = [
            'icetea_pessego' => ['Bebidas', 'IceTea Pessego', 1.50],
            'icetea_limao' => ['Bebidas', 'IceTea Limao', 1.50],
            'icetea_manga' => ['Bebidas', 'IceTea Manga', 1.50],
            'sumol_ananas' => ['Bebidas', 'Sumol Ananas', 1.50],
            'sumol_laranja' => ['Bebidas', 'Sumol Laranja', 1.50],
            'sangria' => ['Bebidas', 'Sangria', 3.00],
            'vinho_tinto' => ['Bebidas', 'Vinho tinto', 2.00],
            'vinho_branco' => ['Bebidas', 'Vinho branco', 2.00],
            'frango' => ['Frango', 'Frango assado', 8.00],
            'batata' => ['Acompanhamentos', 'Batata frita', 2.50],
            'salada' => ['Acompanhamentos', 'Salada', 3.00],
            'pao' => ['Acompanhamentos', 'Pao', 0.50],
        ];

        return collect($produtos)->mapWithKeys(function (array $produto, string $key) {
            [$categoriaNome, $nome, $preco] = $produto;
            $categoria = Categoria::where('nome', $categoriaNome)->firstOrFail();

            return [
                $key => Produto::updateOrCreate(
                    ['nome' => $nome],
                    ['categoria_id' => $categoria->id, 'preco' => $preco, 'disponivel' => true],
                ),
            ];
        })->all();
    }

    private function socios(): array
    {
        $socios = [
            ['D901', 'Ana Martins', 'ana.martins.demo@example.test', '910000901', 'ativo'],
            ['D902', 'Bruno Ferreira', 'bruno.ferreira.demo@example.test', '910000902', 'ativo'],
            ['D903', 'Carla Sousa', 'carla.sousa.demo@example.test', '910000903', 'ativo'],
            ['D904', 'Diogo Almeida', 'diogo.almeida.demo@example.test', '910000904', 'ativo'],
            ['D905', 'Helena Costa', 'helena.costa.demo@example.test', '910000905', 'ativo'],
            ['D906', 'Miguel Ramos', 'miguel.ramos.demo@example.test', '910000906', 'inativo'],
        ];

        return collect($socios)->map(function (array $row, int $index) {
            return Socio::create([
                'numero_socio' => $row[0],
                'nome' => $row[1],
                'email' => $row[2],
                'telefone' => $row[3],
                'morada' => 'Rua da Demo, '.($index + 1),
                'data_nascimento' => now()->subYears(28 + $index * 4)->toDateString(),
                'data_inscricao' => now()->subMonths(18 - $index)->toDateString(),
                'estado' => $row[4],
            ]);
        })->all();
    }

    private function cotas(array $socios): void
    {
        foreach ($socios as $index => $socio) {
            foreach (range(0, 5) as $offset) {
                $data = now()->startOfMonth()->subMonths($offset);
                $emAtraso = $index >= 3 && $offset < 2;

                Cota::create([
                    'socio_id' => $socio->id,
                    'ano' => $data->year,
                    'mes' => $data->month,
                    'tipo' => 'mensal',
                    'valor' => 5,
                    'data_pagamento' => $emAtraso ? null : $data->copy()->addDays(4)->toDateString(),
                    'data_vencimento' => $data->copy()->endOfMonth()->toDateString(),
                    'estado' => $emAtraso ? 'em_atraso' : 'pago',
                    'metodo_pagamento' => $emAtraso ? null : ['dinheiro', 'mbway', 'transferencia'][$offset % 3],
                    'observacoes' => self::MARK.' Cota demonstracao',
                ]);
            }
        }
    }

    private function reservas(): void
    {
        $reservas = [
            ['Familia Pereira', 4, 6, '20:00', 6, 'confirmada'],
            ['Grupo Coral', 8, 10, '20:30', 10, 'confirmada'],
            ['Direcao ARDC', 2, 1, '13:00', 5, 'sentada'],
            ['Aniversario Sofia', 12, 14, '21:00', 12, 'confirmada'],
            ['Mesa cancelada demo', 16, -1, '19:30', 4, 'cancelada'],
        ];

        foreach ($reservas as [$nome, $mesaNumero, $days, $hora, $pessoas, $estado]) {
            $mesa = Mesa::where('numero', $mesaNumero)->first();

            Reserva::create([
                'mesa_id' => $mesa?->id,
                'nome' => $nome,
                'telefone' => '91988'.str_pad((string) $mesaNumero, 4, '0', STR_PAD_LEFT),
                'data' => now()->addDays($days)->toDateString(),
                'hora' => $hora,
                'pessoas' => $pessoas,
                'estado' => $estado,
            ]);
        }
    }

    private function pedidos(array $users, array $produtos): void
    {
        $pedidos = [
            [
                'mesa' => 1,
                'tipo' => 'restaurante',
                'estado' => 'pendente',
                'itens' => [['frango', 2], ['batata', 2], ['salada', 1], ['vinho_tinto', 2]],
                'pago' => null,
                'troco' => 0,
                'metodo' => null,
                'created_at' => now()->subMinutes(20),
            ],
            [
                'mesa' => 3,
                'tipo' => 'restaurante',
                'estado' => 'preparacao',
                'itens' => [['frango', 4], ['batata', 4], ['pao', 4], ['sumol_laranja', 4]],
                'pago' => null,
                'troco' => 0,
                'metodo' => null,
                'created_at' => now()->subMinutes(45),
            ],
            [
                'mesa' => 5,
                'tipo' => 'restaurante',
                'estado' => 'entregue',
                'itens' => [['frango', 3], ['batata', 3], ['salada', 2], ['sangria', 2]],
                'pago' => 55,
                'troco' => 2,
                'metodo' => 'dinheiro',
                'created_at' => now()->subHours(2),
            ],
            [
                'mesa' => null,
                'tipo' => 'bar_prepago',
                'estado' => 'entregue',
                'itens' => [['icetea_pessego', 3], ['icetea_limao', 2], ['sumol_ananas', 3]],
                'pago' => 15,
                'troco' => 3,
                'metodo' => 'mbway',
                'ponto_bar' => 'Bar 1',
                'created_at' => now()->subMinutes(70),
            ],
            [
                'mesa' => null,
                'tipo' => 'bar_conta',
                'estado' => 'pronto',
                'itens' => [['vinho_branco', 4], ['icetea_manga', 2]],
                'pago' => null,
                'troco' => 0,
                'metodo' => null,
                'ponto_bar' => 'Bar 2',
                'created_at' => now()->subMinutes(12),
            ],
        ];

        foreach ($pedidos as $index => $dados) {
            $mesa = $dados['mesa'] ? Mesa::where('numero', $dados['mesa'])->first() : null;
            $pos = PosSession::where('localizacao', $dados['ponto_bar'] ?? 'Restaurante')->first();
            $total = collect($dados['itens'])->sum(fn (array $item) => $produtos[$item[0]]->preco * $item[1]);
            $valorRecebido = $dados['pago'];
            $troco = $dados['troco'];

            $pedido = Pedido::create([
                'mesa_id' => $mesa?->id,
                'user_id' => $users['restaurante']->id,
                'pos_id' => $pos?->id,
                'estado' => $dados['estado'],
                'tipo' => $dados['tipo'],
                'numero_senha' => $dados['mesa'] ? null : 100 + $index,
                'pago_antecipado' => $dados['tipo'] === 'bar_prepago',
                'ponto_bar' => $dados['ponto_bar'] ?? null,
                'total' => $total,
                'valor_recebido' => $valorRecebido,
                'troco' => $troco,
                'doacao' => $valorRecebido ? max(0, $valorRecebido - $total - $troco) : 0,
                'metodo_pagamento' => $dados['metodo'],
                'observacoes' => self::MARK.' Pedido demonstracao',
            ]);

            $pedido->forceFill([
                'created_at' => $dados['created_at'],
                'updated_at' => $dados['created_at'],
            ])->save();

            foreach ($dados['itens'] as [$produtoKey, $quantidade]) {
                $produto = $produtos[$produtoKey];

                PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'produto_id' => $produto->id,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $produto->preco,
                    'estado' => in_array($dados['estado'], ['pronto', 'entregue'], true) ? 'pronto' : 'pendente',
                    'secao' => $produto->categoria->secao,
                    'prioridade' => $dados['estado'] === 'pendente' && $quantidade >= 2,
                    'observacoes' => self::MARK.' Item demonstracao',
                ]);
            }
        }
    }

    private function caixas(array $users): void
    {
        $caixas = [
            ['Restaurante', 80, 426.50, 0],
            ['Bar 1', 50, 218.00, 1.50],
            ['Bar 2', 50, 174.50, -0.50],
            ['Cafe', 40, 96.00, 0],
        ];

        foreach ($caixas as [$ponto, $fundo, $contado, $diferenca]) {
            CaixaDiaria::updateOrCreate(
                ['data' => now()->toDateString(), 'ponto' => $ponto],
                [
                    'fundo_maneio' => $fundo,
                    'estado' => 'fechada',
                    'valor_contado' => $contado,
                    'diferenca' => $diferenca,
                    'observacoes_fecho' => self::MARK.' Caixa demonstracao',
                    'user_id' => $users['tesoureiro']->id,
                    'fechado_user_id' => $users['gerente']->id,
                    'fechado_at' => now()->subMinutes(10),
                ],
            );
        }
    }

    private function reservaNomes(): array
    {
        return [
            'Familia Pereira',
            'Grupo Coral',
            'Direcao ARDC',
            'Aniversario Sofia',
            'Mesa cancelada demo',
        ];
    }
}
