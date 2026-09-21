<?php

namespace App\Console\Commands;

use App\Models\EventoMedia;
use App\Models\Sponsor;
use App\Models\SponsorImage;
use App\Support\OtimizadorImagem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Reduz o peso das fotos (e, se houver ffmpeg, dos vídeos) que já estão no site.
 *
 *   php artisan media:otimizar                # simulação: só mostra o que ia fazer
 *   php artisan media:otimizar --gravar       # otimiza mesmo as fotos
 *   php artisan media:otimizar --gravar --videos   # também comprime os vídeos (precisa de ffmpeg)
 */
class OtimizarMedia extends Command
{
    protected $signature = 'media:otimizar
        {--gravar : Aplica as alterações (sem esta opção é só simulação)}
        {--videos : Também comprime os vídeos, se o ffmpeg estiver instalado}
        {--crf=30 : Qualidade do vídeo (quanto maior, menor o ficheiro)}';

    protected $description = 'Otimiza as fotos e vídeos já carregados (WebP, redimensionamento e miniaturas)';

    private int $antes = 0;

    private int $depois = 0;

    public function handle(): int
    {
        if (! OtimizadorImagem::disponivel()) {
            $this->error('A extensão GD com suporte WebP não está disponível neste PHP. Nada a fazer.');

            return self::FAILURE;
        }

        $gravar = (bool) $this->option('gravar');

        $this->fotosDeEventos($gravar);
        $this->cartazes($gravar);
        $this->patrocinadores($gravar);

        if ($this->option('videos')) {
            $this->videos($gravar);
        }

        $this->newLine();
        $this->line('Antes:  '.$this->mb($this->antes));
        $this->line('Depois: '.$this->mb($this->depois));
        $poupado = max(0, $this->antes - $this->depois);
        $this->info('Poupança: '.$this->mb($poupado).($this->antes ? ' ('.round($poupado / $this->antes * 100).'%)' : ''));

        if (! $gravar) {
            $this->newLine();
            $this->warn('Simulação — repete com --gravar para aplicar.');
        }

        return self::SUCCESS;
    }

    private function fotosDeEventos(bool $gravar): void
    {
        $fotos = EventoMedia::where('tipo', 'foto')->where('aprovado', true)->get();
        $this->line("Fotos de eventos: {$fotos->count()}");

        foreach ($fotos as $media) {
            if (! str_starts_with($media->caminho, '/images/')) {
                continue; // imagens de redes sociais (link externo)
            }

            $novo = $this->otimizar($media->caminho, OtimizadorImagem::MAX_LADO, $gravar);

            if ($gravar && $novo) {
                $media->update([
                    'caminho' => $novo,
                    'miniatura' => OtimizadorImagem::miniaturaPublica($novo),
                ]);
            }
        }
    }

    private function cartazes(bool $gravar): void
    {
        $eventos = \App\Models\Evento::whereNotNull('cartaz')->get(['id', 'cartaz']);
        $this->line("Cartazes: {$eventos->count()}");

        foreach ($eventos as $evento) {
            if (! str_starts_with((string) $evento->cartaz, '/images/')) {
                continue;
            }

            $novo = $this->otimizar($evento->cartaz, 1400, $gravar);

            if ($gravar && $novo) {
                $evento->update(['cartaz' => $novo]);
            }
        }
    }

    private function patrocinadores(bool $gravar): void
    {
        $imagens = SponsorImage::all();
        $logos = Sponsor::whereNotNull('logotipo')->get(['id', 'logotipo']);
        $this->line("Patrocinadores: {$logos->count()} logótipos + {$imagens->count()} imagens");

        foreach ($imagens as $imagem) {
            $novo = $this->otimizar($imagem->path, 1200, $gravar);
            if ($gravar && $novo) {
                $imagem->update(['path' => $novo]);
            }
        }

        foreach ($logos as $sponsor) {
            $novo = $this->otimizar($sponsor->logotipo, 800, $gravar);
            if ($gravar && $novo) {
                $sponsor->update(['logotipo' => $novo]);
            }
        }
    }

    /** Devolve o novo caminho web quando muda, ou null. */
    private function otimizar(?string $caminhoWeb, int $maxLado, bool $gravar): ?string
    {
        if (! $caminhoWeb || ! OtimizadorImagem::otimizavel($caminhoWeb)) {
            return null;
        }

        $absoluto = public_path(ltrim($caminhoWeb, '/'));
        if (! is_file($absoluto)) {
            return null;
        }

        $tamanho = filesize($absoluto);
        $this->antes += $tamanho;

        if (! $gravar) {
            $this->depois += (int) ($tamanho * 0.25); // estimativa só para o resumo
            $this->line("  [simulação] {$caminhoWeb} (".$this->mb($tamanho).')');

            return null;
        }

        $novo = OtimizadorImagem::otimizarPublico($caminhoWeb, $maxLado);
        $novoAbsoluto = public_path(ltrim($novo, '/'));
        $this->depois += is_file($novoAbsoluto) ? filesize($novoAbsoluto) : $tamanho;
        $this->line("  {$caminhoWeb} → {$novo}");

        return $novo === $caminhoWeb ? $caminhoWeb : $novo;
    }

    /** Vídeos: só com ffmpeg instalado no servidor. */
    private function videos(bool $gravar): void
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));

        if ($ffmpeg === '') {
            $this->warn('ffmpeg não encontrado no servidor — vídeos ignorados.');

            return;
        }

        $videos = EventoMedia::where('tipo', 'video')->where('aprovado', true)->get()
            ->filter(fn ($m) => str_starts_with((string) $m->caminho, '/images/'));
        $this->line("Vídeos: {$videos->count()}");

        foreach ($videos as $media) {
            $absoluto = public_path(ltrim($media->caminho, '/'));
            if (! is_file($absoluto)) {
                continue;
            }

            $tamanho = filesize($absoluto);
            $this->antes += $tamanho;

            $destino = preg_replace('/\.[^.]+$/', '', $absoluto).'-web.mp4';
            $crf = (int) $this->option('crf');

            if (! $gravar) {
                $this->depois += (int) ($tamanho * 0.4);
                $this->line("  [simulação] {$media->caminho} (".$this->mb($tamanho).')');

                continue;
            }

            $comando = sprintf(
                '%s -y -i %s -vf "scale=\'min(1280,iw)\':-2" -c:v libx264 -crf %d -preset medium -c:a aac -b:a 128k -movflags +faststart %s 2>&1',
                escapeshellcmd($ffmpeg),
                escapeshellarg($absoluto),
                $crf,
                escapeshellarg($destino),
            );

            shell_exec($comando);

            if (! is_file($destino) || filesize($destino) >= $tamanho) {
                File::delete($destino);
                $this->depois += $tamanho;
                $this->line("  {$media->caminho} — sem ganho, ficou como estava");

                continue;
            }

            $this->depois += filesize($destino);
            File::delete($absoluto);
            $novoWeb = preg_replace('/\.[^.\/]+$/', '', $media->caminho).'-web.mp4';
            $media->update(['caminho' => $novoWeb]);
            $this->line("  {$media->caminho} → {$novoWeb}");
        }
    }

    private function mb(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
