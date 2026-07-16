<?php

namespace App\Mail;

use App\Models\EventoInscricao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscricaoConfirmadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EventoInscricao $inscricao) {}

    public function build(): self
    {
        $mail = $this
            ->subject('Inscrição confirmada: '.$this->inscricao->evento->titulo.' - ARDC Santana')
            ->view('mail.inscricao-confirmada');

        if ($replyTo = config('mail.reply_to')) {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}
