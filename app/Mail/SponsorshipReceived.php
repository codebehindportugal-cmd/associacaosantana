<?php

namespace App\Mail;

use App\Models\SponsorshipRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorshipReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SponsorshipRequest $pedido) {}

    public function build(): self
    {
        return $this
            ->subject('Novo pedido de patrocínio - ARDC Santana')
            ->replyTo($this->pedido->email, $this->pedido->nome)
            ->view('mail.sponsorship-received');
    }
}
