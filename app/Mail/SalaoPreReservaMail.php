<?php

namespace App\Mail;

use App\Models\Aluguer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalaoPreReservaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Aluguer $aluguer) {}

    public function build(): self
    {
        $mail = $this
            ->subject('Pré-reserva do Salão recebida - ARDC Santana')
            ->view('mail.salao-pre-reserva');

        if ($replyTo = config('mail.reply_to')) {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}
