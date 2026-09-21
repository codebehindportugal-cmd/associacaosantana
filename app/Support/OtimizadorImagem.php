<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Reduz o peso das imagens do site: redimensiona, converte para WebP e cria
 * uma miniatura para as grelhas (galerias de eventos com muitas fotos).
 *
 * Usa só a extensão GD (já vem com o PHP), sem dependências novas.
 * Se o GD ou o WebP não estiverem disponíveis, devolve o ficheiro original
 * sem o estragar — o site continua a funcionar, só sem otimização.
 */
class OtimizadorImagem
{
    /** Lado maior da imagem publicada (chega para ecrãs grandes). */
    public const MAX_LADO = 1920;

    /** Lado maior da miniatura usada nas grelhas. */
    public const MAX_LADO_MINIATURA = 640;

    public const QUALIDADE = 78;

    public const QUALIDADE_MINIATURA = 70;

    /** Acima disto não se arrisca a descomprimir (memória do servidor). */
    private const MAX_PIXEIS = 60_000_000;

    private const EXTENSOES = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

    public static function disponivel(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    public static function otimizavel(string $caminho): bool
    {
        return in_array(strtolower(pathinfo($caminho, PATHINFO_EXTENSION)), self::EXTENSOES, true);
    }

    /**
     * Otimiza uma imagem guardada em public/ (caminho web tipo /images/...).
     * Devolve o novo caminho web (.webp) ou o original se não der para otimizar.
     */
    public static function otimizarPublico(string $caminhoWeb, int $maxLado = self::MAX_LADO, int $qualidade = self::QUALIDADE): string
    {
        if (! str_starts_with($caminhoWeb, '/') || ! self::otimizavel($caminhoWeb)) {
            return $caminhoWeb;
        }

        $absoluto = public_path(ltrim($caminhoWeb, '/'));
        $destino = self::trocarExtensao($absoluto, 'webp');

        if (! self::processar($absoluto, $destino, $maxLado, $qualidade)) {
            return $caminhoWeb;
        }

        if ($destino !== $absoluto) {
            File::delete($absoluto);
        }

        return self::trocarExtensao($caminhoWeb, 'webp');
    }

    /**
     * Cria a miniatura ao lado da imagem (ficheiro "-mini.webp").
     * Devolve o caminho web da miniatura, ou null se não for possível.
     */
    public static function miniaturaPublica(string $caminhoWeb, int $maxLado = self::MAX_LADO_MINIATURA): ?string
    {
        if (! str_starts_with($caminhoWeb, '/') || ! self::otimizavel($caminhoWeb)) {
            return null;
        }

        $absoluto = public_path(ltrim($caminhoWeb, '/'));
        $miniWeb = self::caminhoMiniatura($caminhoWeb);
        $miniAbsoluto = public_path(ltrim($miniWeb, '/'));

        return self::processar($absoluto, $miniAbsoluto, $maxLado, self::QUALIDADE_MINIATURA) ? $miniWeb : null;
    }

    /** Otimiza um ficheiro em qualquer sítio, no próprio lugar. Devolve o novo caminho absoluto. */
    public static function otimizarFicheiro(string $absoluto, int $maxLado = self::MAX_LADO, int $qualidade = self::QUALIDADE): string
    {
        if (! self::otimizavel($absoluto)) {
            return $absoluto;
        }

        $destino = self::trocarExtensao($absoluto, 'webp');

        if (! self::processar($absoluto, $destino, $maxLado, $qualidade)) {
            return $absoluto;
        }

        if ($destino !== $absoluto) {
            File::delete($absoluto);
        }

        return $destino;
    }

    public static function caminhoMiniatura(string $caminho): string
    {
        $semExtensao = preg_replace('/\.[^.\/]+$/', '', $caminho);

        return $semExtensao.'-mini.webp';
    }

    /** Lê, redimensiona e grava em WebP. Devolve false quando não dá (e nada é alterado). */
    private static function processar(string $origem, string $destino, int $maxLado, int $qualidade): bool
    {
        if (! self::disponivel() || ! is_file($origem)) {
            return false;
        }

        $info = @getimagesize($origem);
        if (! $info || $info[0] * $info[1] > self::MAX_PIXEIS) {
            return false;
        }

        // Já está em WebP e dentro do tamanho: não se volta a comprimir (perde qualidade à toa)
        if ($origem === $destino && ($info['mime'] ?? '') === 'image/webp' && max($info[0], $info[1]) <= $maxLado) {
            return false;
        }

        try {
            $imagem = @imagecreatefromstring((string) file_get_contents($origem));
            if (! $imagem) {
                return false;
            }

            $imagem = self::corrigirOrientacao($imagem, $origem, $info['mime'] ?? '');

            $largura = imagesx($imagem);
            $altura = imagesy($imagem);
            $escala = min(1, $maxLado / max($largura, $altura));

            if ($escala < 1) {
                $nova = imagescale($imagem, (int) round($largura * $escala), (int) round($altura * $escala), IMG_BICUBIC);
                if ($nova) {
                    imagedestroy($imagem);
                    $imagem = $nova;
                }
            }

            imagepalettetotruecolor($imagem);
            imagealphablending($imagem, true);
            imagesavealpha($imagem, true);

            File::ensureDirectoryExists(dirname($destino));
            $temporario = $destino.'.tmp';
            $ok = imagewebp($imagem, $temporario, $qualidade);
            imagedestroy($imagem);

            if (! $ok || ! is_file($temporario)) {
                File::delete($temporario);

                return false;
            }

            // Se a conversão não compensar (ficou maior), fica o original
            if ($destino !== $origem && filesize($temporario) >= filesize($origem) && $maxLado >= self::MAX_LADO) {
                File::delete($temporario);

                return false;
            }

            File::move($temporario, $destino);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Otimização de imagem falhou', ['ficheiro' => $origem, 'erro' => $e->getMessage()]);

            return false;
        }
    }

    /** Fotos de telemóvel vêm deitadas: roda-as pelo EXIF antes de gravar. */
    private static function corrigirOrientacao(\GdImage $imagem, string $origem, string $mime): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return $imagem;
        }

        try {
            $exif = @exif_read_data($origem);
            $angulo = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($angulo === 0) {
                return $imagem;
            }

            $rodada = imagerotate($imagem, $angulo, 0);
            if ($rodada) {
                imagedestroy($imagem);

                return $rodada;
            }
        } catch (\Throwable) {
            // sem EXIF utilizável
        }

        return $imagem;
    }

    private static function trocarExtensao(string $caminho, string $extensao): string
    {
        return preg_replace('/\.[^.\/]+$/', '', $caminho).'.'.$extensao;
    }
}
