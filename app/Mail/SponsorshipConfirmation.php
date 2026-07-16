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
        $mail = $this
            ->subject('Recebemos a sua proposta de patrocínio - ARDC Santana')
            ->view('mail.sponsorship-confirmation');

        if ($replyTo = config('mail.reply_to')) {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}
