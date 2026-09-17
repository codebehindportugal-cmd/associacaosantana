<?php

namespace App\Console\Commands;

use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa a lista de socios a partir de um CSV.
 *
 * Feito para a lista real da associacao, que traz repetidos: o numero de socio
 * manda, e quando aparece duas vezes fica so o primeiro registo. Nada e escrito
 * sem --gravar, para se poder ver primeiro o que ia acontecer.
 *
 *   php artisan socios:importar lista.csv
 *   php artisan socios:importar lista.csv --gravar
 */
class ImportarSocios extends Command
{
    protected $signature = 'socios:importar
        {ficheiro : Caminho do CSV}
        {--gravar : Grava mesmo. Sem esta opcao so mostra o que ia acontecer}
        {--atualizar : Atualiza os socios que ja existem, em vez de os saltar}
        {--separador= : Separador do CSV, se a deteccao automatica falhar}';

    protected $description = 'Importa socios de um CSV, ignorando numeros de socio repetidos';

    /** Como cada coluna do ficheiro e reconhecida, sem depender do nome exacto. */
    private const COLUNAS = [
        'numero_socio' => ['numero socio', 'numero de socio', 'numero', 'n socio', 'no socio', 'nsocio', 'socio', 'num'],
        'nome' => ['nome', 'nome completo', 'socio nome'],
        'email' => ['email', 'e mail', 'correio electronico'],
        'telefone' => ['telefone', 'telemovel', 'contacto', 'tlm', 'tel'],
        'morada' => ['morada', 'endereco', 'residencia'],
        'data_nascimento' => ['data nascimento', 'data de nascimento', 'nascimento', 'dt nascimento'],
        'data_inscricao' => ['data inscricao', 'data de inscricao', 'inscricao', 'admissao', 'data admissao'],
        'estado' => ['estado', 'situacao', 'ativo'],
    ];

    public function handle(): int
    {
        $caminho = $this->argument('ficheiro');

        if (! is_file($caminho)) {
            $this->error("Nao encontrei o ficheiro: {$caminho}");

            return self::FAILURE;
        }

        $linhas = $this->lerCsv($caminho);

        if ($linhas === []) {
            $this->error('O ficheiro esta vazio ou nao consegui ler o cabecalho.');

            return self::FAILURE;
        }

        $mapa = $this->mapearColunas(array_keys($linhas[0]));

        if (! isset($mapa['numero_socio'], $mapa['nome'])) {
            $this->error('Faltam colunas obrigatorias: numero de socio e nome.');
            $this->line('Colunas encontradas: '.implode(', ', array_keys($linhas[0])));

            return self::FAILURE;
        }

        $existentes = Socio::pluck('id', 'numero_socio');
        $vistos = [];
        $novos = [];
        $repetidosNoFicheiro = [];
        $jaExistiam = [];
        $semDados = [];

        foreach ($linhas as $indice => $linha) {
            $numero = trim((string) ($linha[$mapa['numero_socio']] ?? ''));
            $nome = trim((string) ($linha[$mapa['nome']] ?? ''));
            $numeroLinha = $indice + 2; // +1 do cabecalho, +1 porque as pessoas contam de 1

            if ($numero === '' || $nome === '') {
                $semDados[] = ['linha' => $numeroLinha, 'numero' => $numero, 'nome' => $nome];

                continue;
            }

            // O numero manda: a primeira ocorrencia fica, as outras sao ignoradas
            if (isset($vistos[$numero])) {
                $repetidosNoFicheiro[] = [
                    'linha' => $numeroLinha,
                    'numero' => $numero,
                    'nome' => $nome,
                    'ficou' => $vistos[$numero],
                ];

                continue;
            }

            $vistos[$numero] = $nome;

            if (isset($existentes[$numero]) && ! $this->option('atualizar')) {
                $jaExistiam[] = ['numero' => $numero, 'nome' => $nome];

                continue;
            }

            $novos[] = [
                'numero_socio' => $numero,
                'nome' => $nome,
                'email' => $this->valor($linha, $mapa, 'email'),
                'telefone' => $this->valor($linha, $mapa, 'telefone'),
                'morada' => $this->valor($linha, $mapa, 'morada'),
                'data_nascimento' => $this->data($this->valor($linha, $mapa, 'data_nascimento')),
                'data_inscricao' => $this->data($this->valor($linha, $mapa, 'data_inscricao')) ?? now()->toDateString(),
                'estado' => $this->estado($this->valor($linha, $mapa, 'estado')),
            ];
        }

        $this->resumo($linhas, $novos, $repetidosNoFicheiro, $jaExistiam, $semDados);

        if (! $this->option('gravar')) {
            $this->newLine();
            $this->warn('Simulacao. Repete com --gravar para escrever na base de dados.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($novos) {
            foreach ($novos as $dados) {
                Socio::updateOrCreate(['numero_socio' => $dados['numero_socio']], $dados);
            }
        });

        $this->newLine();
        $this->info(count($novos).' socio(s) gravado(s).');

        return self::SUCCESS;
    }

    private function resumo(array $linhas, array $novos, array $repetidos, array $jaExistiam, array $semDados): void
    {
        $this->newLine();
        $this->line('Linhas no ficheiro: '.count($linhas));
        $this->line('A importar: '.count($novos));
        $this->line('Repetidos no ficheiro (ignorados): '.count($repetidos));
        $this->line('Ja existiam na base de dados: '.count($jaExistiam));
        $this->line('Sem numero ou sem nome: '.count($semDados));

        if ($repetidos !== []) {
            $this->newLine();
            $this->warn('Numeros de socio repetidos — ficou sempre o primeiro:');
            $this->table(
                ['Linha', 'Numero', 'Nome ignorado', 'Nome que ficou'],
                array_map(fn ($r) => [$r['linha'], $r['numero'], $r['nome'], $r['ficou']], $repetidos),
            );
        }

        if ($semDados !== []) {
            $this->newLine();
            $this->warn('Linhas sem numero de socio ou sem nome — nao importadas:');
            $this->table(
                ['Linha', 'Numero', 'Nome'],
                array_map(fn ($r) => [$r['linha'], $r['numero'] ?: '—', $r['nome'] ?: '—'], $semDados),
            );
        }
    }

    private function lerCsv(string $caminho): array
    {
        $conteudo = file_get_contents($caminho);

        // Ficheiros do Excel costumam vir em UTF-8 com BOM ou em ISO-8859-1
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }

        $primeiraLinha = strtok($conteudo, "\r\n") ?: '';
        $separador = $this->option('separador')
            ?: (substr_count($primeiraLinha, ';') > substr_count($primeiraLinha, ',') ? ';' : ',');

        $temporario = fopen('php://temp', 'r+');
        fwrite($temporario, $conteudo);
        rewind($temporario);

        $cabecalho = fgetcsv($temporario, 0, $separador);

        if (! $cabecalho) {
            fclose($temporario);

            return [];
        }

        $cabecalho = array_map(fn ($coluna) => trim((string) $coluna), $cabecalho);
        $linhas = [];

        while (($dados = fgetcsv($temporario, 0, $separador)) !== false) {
            if ($dados === [null] || $dados === false) {
                continue;
            }

            // Linhas totalmente vazias nao contam
            if (count(array_filter($dados, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $dados = array_pad(array_slice($dados, 0, count($cabecalho)), count($cabecalho), null);
            $linhas[] = array_combine($cabecalho, $dados);
        }

        fclose($temporario);

        return $linhas;
    }

    private function mapearColunas(array $cabecalho): array
    {
        $mapa = [];

        foreach ($cabecalho as $coluna) {
            $normalizada = $this->normalizar($coluna);

            foreach (self::COLUNAS as $campo => $hipoteses) {
                if (isset($mapa[$campo])) {
                    continue;
                }

                if (in_array($normalizada, $hipoteses, true)) {
                    $mapa[$campo] = $coluna;
                    break;
                }
            }
        }

        return $mapa;
    }

    /** Tira acentos, pontuacao e maiusculas, para comparar nomes de colunas. */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ç' => 'c', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    private function valor(array $linha, array $mapa, string $campo): ?string
    {
        if (! isset($mapa[$campo])) {
            return null;
        }

        $valor = trim((string) ($linha[$mapa[$campo]] ?? ''));

        return $valor === '' ? null : $valor;
    }

    private function data(?string $valor): ?string
    {
        if (! $valor) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'Y/m/d', 'd.m.Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $valor)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function estado(?string $valor): string
    {
        if (! $valor) {
            return 'ativo';
        }

        $normalizado = $this->normalizar($valor);

        return in_array($normalizado, ['inativo', 'nao', 'n', '0', 'suspenso', 'desistiu', 'anulado'], true)
            ? 'inativo'
            : 'ativo';
    }
}
