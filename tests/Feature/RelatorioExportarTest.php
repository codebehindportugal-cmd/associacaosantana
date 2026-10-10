<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RelatorioExportarTest extends TestCase
{
    use RefreshDatabase;

    private function preparar(): User
    {
        $categoria = Categoria::create(['nome' => 'Bebidas', 'secao' => 'bebidas']);
        $pedido = Pedido::create(['tipo' => 'bar_prepago', 'estado' => 'pronto', 'pago_antecipado' => true, 'total' => 0]);
        foreach (range(1, 15) as $i) {
            $produto = Produto::create(['categoria_id' => $categoria->id, 'nome' => "Produto $i", 'preco' => 2, 'disponivel' => true]);
            $pedido->items()->create(['produto_id' => $produto->id, 'quantidade' => $i, 'preco_unitario' => 2]);
        }

        Permission::findOrCreate('relatorios.ver');
        $user = User::factory()->create();
        $user->givePermissionTo('relatorios.ver');

        return $user;
    }

    public function test_csv_tem_todos_os_produtos_e_so_as_colunas_escolhidas(): void
    {
        $user = $this->preparar();
        $hoje = now()->subHours(12)->toDateString();

        $res = $this->actingAs($user)->get(route('relatorios.pdf', [
            'data_inicio' => $hoje, 'data_fim' => $hoje, 'formato' => 'csv', 'colunas' => ['quantidade', 'total'],
        ]));

        $res->assertOk();
        $linhas = array_values(array_filter(explode("\n", ltrim($res->streamedContent(), "\xEF\xBB\xBF"))));
        $this->assertSame('Produto;Qtd;Total', trim($linhas[0]));
        $this->assertCount(16, $linhas);
        $this->assertSame('"Produto 15";15;30,00', trim($linhas[1]));
    }

    public function test_pdf_exporta_todos_ou_top10(): void
    {
        $user = $this->preparar();
        $hoje = now()->subHours(12)->toDateString();

        $this->actingAs($user)->get(route('relatorios.pdf', ['data_inicio' => $hoje, 'data_fim' => $hoje]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)->get(route('relatorios.pdf', [
            'data_inicio' => $hoje, 'data_fim' => $hoje, 'produtos' => 'top10', 'seccoes' => ['produtos'], 'colunas' => ['categoria'],
        ]))->assertOk();
    }
}
