<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function build(): self
    {
        return $this
            ->subject('Novo contacto pelo site ARDC Santana')
            ->replyTo($this->data['email'], $this->data['name'])
            ->view('mail.contact-association');
    }
}
