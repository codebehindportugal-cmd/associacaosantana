<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function build(): self
    {
        $mail = $this
            ->subject('Recebemos a tua mensagem - ARDC Santana')
            ->view('mail.contact-confirmation');

        if ($replyTo = config('mail.reply_to')) {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}
