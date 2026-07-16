<?php

namespace App\Console\Commands;

use App\Services\VivaPayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class VivaTeste extends Command
{
    protected $signature = 'viva:teste';

    protected $description = 'Testa a configuração Viva: credenciais, token e criação de ordem';

    public function handle(VivaPayments $viva): int
    {
        $this->line('Ambiente:    '.config('services.viva.env', 'demo'));
        $this->line('Client ID:   '.(config('services.viva.client_id') ? substr((string) config('services.viva.client_id'), 0, 12).'...' : 'VAZIO'));
        $this->line('Secret:      '.(config('services.viva.client_secret') ? 'definido' : 'VAZIO'));
        $this->line('Source code: '.(config('services.viva.source_code') ?: 'VAZIO'));
        $this->line('');

        if (! $viva->configurado()) {
            $this->error('NAO CONFIGURADO: falta pelo menos uma das variáveis VIVA_* no .env');
            $this->line('Depois de as definir: php artisan config:clear');

            return self::FAILURE;
        }

        // 1. Token OAuth — testa o ambiente configurado E o outro, para
        // identificar a que ambiente pertencem as credenciais
        $ambientes = [
            'demo' => 'https://demo-accounts.vivapayments.com',
            'live' => 'https://accounts.vivapayments.com',
        ];
        $atual = $viva->demo() ? 'demo' : 'live';
        $token = null;

        foreach ($ambientes as $nome => $url) {
            try {
                $resposta = Http::asForm()
                    ->withBasicAuth(config('services.viva.client_id'), config('services.viva.client_secret'))
                    ->post($url.'/connect/token', ['grant_type' => 'client_credentials']);

                if ($resposta->successful()) {
                    $this->info("[token] Credenciais VÁLIDAS no ambiente: {$nome}");
                    if ($nome === $atual) {
                        $token = $resposta->json('access_token');
                    }
                } else {
                    $this->line("[token] {$nome}: inválidas (".$resposta->status().')');
                }
            } catch (\Throwable $e) {
                $this->line("[token] {$nome}: erro de rede — ".$e->getMessage());
            }
        }

        if (! $token) {
            $this->error('');
            $this->error('As credenciais NÃO são válidas no ambiente configurado ('.$atual.').');
            $this->line('Se acima disser válidas em "live": ou mudas VIVA_ENV=live (e usas o source code');
            $this->line('da conta real), ou crias credenciais na conta demo (demo.vivapayments.com).');
            $this->line('Se inválidas em ambos: o Client ID/Secret estão errados — copia-os de novo.');

            return self::FAILURE;
        }

        // 2. Criar ordem de teste de 1 cêntimo
        $api = $viva->demo() ? 'https://demo-api.vivapayments.com' : 'https://api.vivapayments.com';
        $this->line('[2/2] Criar ordem de teste (0,01 €) ...');

        $resposta = Http::withToken($token)->post($api.'/checkout/v2/orders', [
            'amount' => 1,
            'customerTrns' => 'Teste de configuracao',
            'sourceCode' => config('services.viva.source_code'),
        ]);

        if (! $resposta->successful()) {
            $this->error('FALHOU ('.$resposta->status().'): '.$resposta->body());
            $this->line('Dica: erro com sourceCode = o source nao existe neste ambiente ou nao esta ativo.');

            return self::FAILURE;
        }

        $orderCode = $resposta->json('orderCode');
        $this->info('ORDEM CRIADA OK — orderCode: '.$orderCode);
        $this->line('Checkout: '.$viva->urlCheckout((string) $orderCode));

        return self::SUCCESS;
    }
}
