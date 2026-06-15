<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SitePageController extends Controller
{
    public function index(): Response
    {
        self::ensureDefaults();

        return Inertia::render('Paginas/Index', [
            'paginas' => SitePage::orderBy('titulo')->get()->map(fn (SitePage $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'titulo' => $page->titulo,
                'updated_at' => optional($page->updated_at)->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function edit(SitePage $pagina): Response
    {
        return Inertia::render('Paginas/Edit', [
            'pagina' => [
                'id' => $pagina->id,
                'slug' => $pagina->slug,
                'titulo' => $pagina->titulo,
                'conteudo' => $pagina->conteudo ?? [],
            ],
        ]);
    }

    public function update(Request $request, SitePage $pagina): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'hero_titulo' => ['nullable', 'string', 'max:255'],
            'hero_subtitulo' => ['nullable', 'string', 'max:500'],
            'introducao' => ['nullable', 'string', 'max:1000'],
            'corpo' => ['nullable', 'string'],
            'extra' => ['nullable', 'string'],
        ]);

        $pagina->update([
            'titulo' => $data['titulo'],
            'conteudo' => [
                'hero_titulo' => $data['hero_titulo'] ?? '',
                'hero_subtitulo' => $data['hero_subtitulo'] ?? '',
                'introducao' => $data['introducao'] ?? '',
                'corpo' => $data['corpo'] ?? '',
                'extra' => $data['extra'] ?? '',
            ],
        ]);

        return back()->with('success', 'Página atualizada.');
    }

    public static function pageData(string $slug): array
    {
        $default = self::defaults()[$slug] ?? ['titulo' => '', 'conteudo' => []];

        if (! Schema::hasTable('site_pages')) {
            return $default;
        }

        $page = SitePage::firstOrCreate(['slug' => $slug], $default);

        return [
            'titulo' => $page->titulo,
            'conteudo' => $page->conteudo ?? [],
        ];
    }

    public static function ensureDefaults(): void
    {
        if (! Schema::hasTable('site_pages')) {
            return;
        }

        foreach (self::defaults() as $slug => $page) {
            SitePage::firstOrCreate(['slug' => $slug], $page);
        }
    }

    public static function defaults(): array
    {
        return [
            'sobre-nos' => [
                'titulo' => 'Sobre Nós',
                'conteudo' => [
                    'hero_titulo' => 'A nossa história, a nossa gente',
                    'hero_subtitulo' => 'A ARDC Santana é uma casa de cultura, desporto e convívio, construída pela dedicação de várias gerações.',
                    'introducao' => 'Uma associação com raízes locais.',
                    'corpo' => "A associação foi fundada para criar um ponto de encontro para Santana e para preservar a vida cultural, recreativa e desportiva da comunidade.\n\nA nossa missão é aproximar pessoas, organizar atividades abertas à população e manter viva a tradição da festa anual.\n\nAo longo do ano promovemos convívios, caminhadas, eventos culturais, iniciativas desportivas e momentos de apoio à comunidade.",
                    'extra' => "1991|Ano de fundação\n250+|Sócios\n30+|Edições da festa\n40+|Voluntários ativos",
                ],
            ],
            'patrocinios' => [
                'titulo' => 'Patrocínios',
                'conteudo' => [
                    'hero_titulo' => 'Apoia a Festa de Santa Ana',
                    'hero_subtitulo' => 'Ajude-nos a manter viva uma festa feita pela comunidade. Cada contributo conta e a visibilidade é combinada consigo.',
                    'introducao' => 'Patrocínio simples, direto e adaptado.',
                    'corpo' => 'Cada patrocinador contribui com o que lhe for possível. Em troca, trabalhamos consigo para dar a máxima visibilidade à vossa marca: no recinto com lonas, nas nossas redes sociais e aqui no nosso site.',
                    'extra' => "Lona no recinto|A sua marca visível durante a festa.\nRedes sociais|Publicação de agradecimento com menção à empresa.\nDestaque no site|Logótipo com link para o site da empresa no slider.",
                ],
            ],
            'privacidade' => [
                'titulo' => 'Política de Privacidade',
                'conteudo' => [
                    'hero_titulo' => 'Política de Privacidade',
                    'hero_subtitulo' => 'Última atualização: 15/06/2026',
                    'corpo' => "A responsável pelo tratamento dos dados pessoais é a ARDC de Santa Ana, com sede em Santana, Carvalhal Benfeito, Caldas da Rainha.\n\nPodemos recolher dados submetidos através dos formulários de contacto e patrocínios, incluindo nome, empresa, email, telefone e mensagem.\n\nOs dados são usados para responder a pedidos, gerir contactos de patrocínio e assegurar o funcionamento do site.\n\nPode solicitar acesso, retificação, apagamento, limitação, portabilidade ou oposição através de ardcsantana@outlook.com.",
                ],
            ],
            'termos' => [
                'titulo' => 'Termos e Condições',
                'conteudo' => [
                    'hero_titulo' => 'Termos e Condições',
                    'hero_subtitulo' => 'Última atualização: 15/06/2026',
                    'corpo' => "Este site é gerido pela ARDC de Santa Ana.\n\nA utilização do site deve respeitar a legislação aplicável e não pode prejudicar a disponibilidade, segurança ou integridade dos serviços.\n\nTextos, imagens, logótipos e demais conteúdos pertencem à ARDC Santana ou aos respetivos titulares.\n\nEstes termos regem-se pela legislação portuguesa.",
                ],
            ],
            'cookies' => [
                'titulo' => 'Política de Cookies',
                'conteudo' => [
                    'hero_titulo' => 'Política de Cookies',
                    'hero_subtitulo' => 'Última atualização: 15/06/2026',
                    'corpo' => "Cookies são pequenos ficheiros guardados no dispositivo do utilizador para permitir funcionalidades do site.\n\nUsamos cookies essenciais para funcionamento técnico e para guardar a preferência de consentimento.\n\nPodem existir cookies analíticos ou de terceiros quando configurados e autorizados.\n\nPode gerir ou apagar cookies nas definições do seu browser.",
                ],
            ],
        ];
    }
}
