<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LembreteCotaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomeSocio,
        public int $mesesAtraso,
        public float $valorDivida,
    ) {}

    public function build(): self
    {
        return $this->subject('Lembrete de pagamento de cota')
            ->view('mail.lembrete-cota');
    }
}
