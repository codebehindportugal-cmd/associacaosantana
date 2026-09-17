<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de talão — um por evento, com um deles marcado como em uso.
 * Aplicado a todos os talões por App\Services\PrintJobService.
 */
class TalaoConfig extends Model
{
    use HasFactory;

    public const TITULO_PADRAO = 'ARDC Santana';

    public const RODAPE_PADRAO = 'Este documento nao serve de fatura';

    protected $table = 'talao_configs';

    protected $fillable = [
        'nome',
        'evento_id',
        'titulo',
        'cabecalho',
        'rodape',
        'instrucoes_individual',
        'rodape_em_pedidos',
        'prepago_apenas_individuais',
        'ativo',
        'em_uso',
    ];

    protected $casts = [
        'rodape_em_pedidos' => 'boolean',
        'prepago_apenas_individuais' => 'boolean',
        'ativo' => 'boolean',
        'em_uso' => 'boolean',
    ];

    private static ?self $memoria = null;

    public function evento()
    {
        return $this->belongsTo(Evento::class);
    }

    /**
     * Modelo em vigor. Se nao houver nenhum (ou a tabela ainda nao existir),
     * devolve os valores antigos para que a impressao nunca falhe.
     */
    public static function atual(): self
    {
        if (static::$memoria instanceof self) {
            return static::$memoria;
        }

        try {
            $config = static::query()->where('em_uso', true)->orderBy('id')->first()
                ?: static::query()->orderBy('id')->first();
        } catch (\Throwable $e) {
            $config = null;
        }

        return static::$memoria = $config ?: new self([
            'titulo' => self::TITULO_PADRAO,
            'rodape' => self::RODAPE_PADRAO,
            'rodape_em_pedidos' => false,
            'ativo' => true,
        ]);
    }

    public static function esquecer(): void
    {
        static::$memoria = null;
    }

    /**
     * Marca este modelo como o que esta em uso, desmarcando os outros.
     */
    public function marcarEmUso(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['em_uso' => false]);
        $this->forceFill(['em_uso' => true])->save();

        static::esquecer();
    }

    /**
     * Em eventos com pre-pagamento o cliente leva um talao por SECCAO, com as
     * unidades listadas, e no fim sai a conta com o total e o troco.
     */
    public function taloesPorSeccao(): bool
    {
        return $this->ativo && (bool) $this->prepago_apenas_individuais;
    }

    public function tituloImpresso(): string
    {
        if (! $this->ativo) {
            return self::TITULO_PADRAO;
        }

        return trim((string) $this->titulo) ?: self::TITULO_PADRAO;
    }

    /**
     * Linhas centradas impressas logo a seguir ao tipo de talão.
     */
    public function linhasCabecalho(): array
    {
        if (! $this->ativo) {
            return [];
        }

        return array_map(
            fn ($linha) => ['texto' => $linha, 'alinhamento' => 'centro'],
            $this->separar($this->cabecalho)
        );
    }

    public function linhasRodape(): array
    {
        if (! $this->ativo) {
            return [self::RODAPE_PADRAO];
        }

        return $this->separar($this->rodape) ?: [self::RODAPE_PADRAO];
    }

    /**
     * Instruções impressas no talão unitário que o cliente leva consigo.
     */
    public function linhasInstrucoes(): array
    {
        if (! $this->ativo) {
            return [];
        }

        return array_map(
            fn ($linha) => ['texto' => $linha, 'alinhamento' => 'centro'],
            $this->separar($this->instrucoes_individual)
        );
    }

    private function separar(?string $texto): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $texto))
            ->map(fn ($linha) => trim($linha))
            ->filter(fn ($linha) => $linha !== '')
            ->values()
            ->all();
    }
}
