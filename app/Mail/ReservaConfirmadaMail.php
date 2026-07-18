<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservaConfirmadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva) {}

    public function build(): self
    {
        $data   = \Carbon\Carbon::parse($this->reserva->data)->format('d/m/Y');
        $hora   = substr($this->reserva->hora, 0, 5);
        $mail   = $this
            ->subject("Reserva confirmada – {$data} às {$hora} – ARDC Santana")
            ->view('mail.reserva-confirmada');

        if ($replyTo = config('mail.reply_to')) {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}
