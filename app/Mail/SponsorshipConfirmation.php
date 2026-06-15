<?php

namespace App\Mail;

use App\Models\SponsorshipRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorshipConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SponsorshipRequest $pedido) {}

    public function build(): self
    {
        return $this
            ->subject('Recebemos a sua proposta de patrocínio - ARDC Santana')
            ->view('mail.sponsorship-confirmation');
    }
}
