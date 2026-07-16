<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Valida um token reCAPTCHA v3. Sem chaves configuradas, passa sempre
 * (permite desenvolver sem captcha). Falhas de rede não bloqueiam.
 */
class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (! $secret) {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Validação anti-robô falhou. Atualiza a página e tenta novamente.');

            return;
        }

        try {
            $resposta = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ])->json();

            if (! ($resposta['success'] ?? false)) {
                Log::warning('reCAPTCHA falhou', [
                    'error_codes' => $resposta['error-codes'] ?? [],
                    'hostname' => $resposta['hostname'] ?? null,
                ]);

                $fail('Validação anti-robô falhou. Tenta novamente.');
            }
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA: erro de rede na verificação', ['erro' => $e->getMessage()]);
            // Não bloquear por falha de rede
        }
    }
}
