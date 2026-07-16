<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EmailTeste extends Command
{
    protected $signature = 'email:teste {para=ardcsantana@outlook.com}';

    protected $description = 'Envia um email de teste e mostra o erro completo se falhar';

    public function handle(): int
    {
        $para = $this->argument('para');

        $this->line('Mailer:   '.config('mail.default'));
        $this->line('Host:     '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->line('From:     '.config('mail.from.address'));
        $this->line('Para:     '.$para);
        $this->line('');

        try {
            Mail::raw('Email de teste do site ARDC Santana - '.now()->format('d/m/Y H:i:s'), function ($mensagem) use ($para) {
                $mensagem->to($para)->subject('TESTE SMTP - ARDC Santana');
            });

            $this->info('EMAIL ENVIADO OK — vê a caixa de entrada (e o spam) de '.$para);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FALHOU: '.get_class($e));
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
