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

        // 1. Token OAuth
        $contas = $viva->demo() ? 'https://demo-accounts.vivapayments.com' : 'https://accounts.vivapayments.com';
        $this->line('[1/2] Obter token em '.$contas.' ...');

        try {
            $resposta = Http::asForm()
                ->withBasicAuth(config('services.viva.client_id'), config('services.viva.client_secret'))
                ->post($contas.'/connect/token', ['grant_type' => 'client_credentials']);

            if (! $resposta->successful()) {
                $this->error('FALHOU ('.$resposta->status().'): '.$resposta->body());
                $this->line('Dica: 401 = credenciais erradas OU de outro ambiente (demo vs live).');

                return self::FAILURE;
            }

            $token = $resposta->json('access_token');
            $this->info('Token OK');
        } catch (\Throwable $e) {
            $this->error('ERRO: '.$e->getMessage());

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
