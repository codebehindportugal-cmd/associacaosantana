<?php

namespace App\Services;

use App\Models\EventoInscricao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Integração com Viva.com Smart Checkout.
 * Docs: https://developer.viva.com/smart-checkout/
 */
class VivaPayments
{
    public function configurado(): bool
    {
        return (bool) (config('services.viva.client_id')
            && config('services.viva.client_secret')
            && config('services.viva.source_code'));
    }

    public function demo(): bool
    {
        return config('services.viva.env', 'demo') !== 'live';
    }

    private function urlContas(): string
    {
        return $this->demo() ? 'https://demo-accounts.vivapayments.com' : 'https://accounts.vivapayments.com';
    }

    private function urlApi(): string
    {
        return $this->demo() ? 'https://demo-api.vivapayments.com' : 'https://api.vivapayments.com';
    }

    public function urlCheckout(string $orderCode): string
    {
        $base = $this->demo() ? 'https://demo.vivapayments.com' : 'https://www.vivapayments.com';

        return $base.'/web/checkout?ref='.$orderCode;
    }

    private function token(): string
    {
        return Cache::remember('viva_token', 3000, function () {
            $resposta = Http::asForm()
                ->withBasicAuth(config('services.viva.client_id'), config('services.viva.client_secret'))
                ->post($this->urlContas().'/connect/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            return $resposta['access_token'];
        });
    }

    /**
     * Cria uma ordem de pagamento e devolve o orderCode.
     */
    public function criarOrdem(EventoInscricao $inscricao): string
    {
        $evento = $inscricao->evento;

        $resposta = Http::withToken($this->token())
            ->post($this->urlApi().'/checkout/v2/orders', [
                'amount' => (int) round($inscricao->valor_estimado * 100), // cêntimos
                'customerTrns' => 'Inscrição: '.$evento->titulo,
                'merchantTrns' => 'inscricao-'.$inscricao->id,
                'sourceCode' => config('services.viva.source_code'),
                'paymentTimeout' => 1800,
                'customer' => [
                    'email' => $inscricao->email,
                    'fullName' => $inscricao->nome,
                    'phone' => preg_replace('/\D/', '', $inscricao->telefone),
                    'countryCode' => 'PT',
                    'requestLang' => 'pt-PT',
                ],
            ])
            ->throw()
            ->json();

        return (string) $resposta['orderCode'];
    }

    /**
     * Verifica uma transação. Devolve true se paga com sucesso (statusId F).
     */
    public function transacaoPaga(string $transactionId): bool
    {
        $resposta = Http::withToken($this->token())
            ->get($this->urlApi().'/checkout/v2/transactions/'.$transactionId)
            ->json();

        return ($resposta['statusId'] ?? null) === 'F';
    }

    public function orderCodeDaTransacao(string $transactionId): ?string
    {
        $resposta = Http::withToken($this->token())
            ->get($this->urlApi().'/checkout/v2/transactions/'.$transactionId)
            ->json();

        return isset($resposta['orderCode']) ? (string) $resposta['orderCode'] : null;
    }
}
