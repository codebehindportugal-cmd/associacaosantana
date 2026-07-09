# Prompt – Melhorias ao site ardcsantana.ateneya.com

## Contexto do projeto

Este site é o site oficial da **ARDC de Santa Ana** (Associação Recreativa, Desportiva e Cultural de Santa Ana), alojado em `ardcsantana.ateneya.com`. O site é uma aplicação **Laravel** com frontend em **Blade + Vue.js** (ou Blade puro, confirmar no código). O objetivo é torná-lo mais apelativo visualmente, adicionar conteúdo e história sobre a associação, criar uma secção de **angariação de patrocínios para a festa anual**, e garantir conformidade legal (RGPD).

---

## Stack técnico

- **Backend:** Laravel (versão atual do projeto)
- **Frontend:** Blade templates + Tailwind CSS (ou Bootstrap, verificar no projeto)
- **Base de dados:** MySQL
- **Email:** Laravel Mail (SMTP configurado no `.env`)
- **Idioma:** Português (Portugal)
- **Servidor:** VPS com Plesk (alojado em `ardcsantana.ateneya.com`)

---

## Objetivos principais

1. Tornar o site mais apelativo visualmente e com mais conteúdo
2. Adicionar história e identidade da associação
3. Criar secção de patrocínios com formulário
4. Adicionar páginas legais obrigatórias (RGPD)

---

## Tarefas a implementar

---

### 1. Página "Sobre Nós / História" (`/sobre-nos`)

#### Route
```php
Route::get('/sobre-nos', [PagesController::class, 'sobreNos'])->name('pages.sobre-nos');
```

#### View (`resources/views/pages/sobre-nos.blade.php`)
Estrutura de secções:

- **Hero section** com imagem de fundo da associação e título: *"A nossa história, a nossa gente"*
- **Bloco de texto** com história resumida da associação — usar placeholder estruturado (a associação preencherá depois):
  - Quando foi fundada
  - Missão e valores
  - Atividades principais
- **Números de destaque** (contadores animados com JS):
  - Ano de fundação
  - Nº de sócios
  - Edições da festa
  - Voluntários ativos
- **Galeria de fotos** de edições anteriores da festa — grid responsivo com lightbox simples
- **Equipa diretiva** — cards com foto, nome e cargo

#### Controller
```php
public function sobreNos()
{
    return view('pages.sobre-nos');
}
```

---

### 2. Secção de Patrocínios (`/patrocinios`)

#### Lógica de negócio

Não existem níveis fixos de patrocínio nem valores mínimos. Cada patrocinador contribui com o valor que achar justo e, em troca, a associação oferece visibilidade conforme o acordado. As formas de divulgação disponíveis são:

- **Lona no recinto** da festa (banner físico com logótipo)
- **Redes sociais** (publicação de agradecimento com menção/tag da empresa)
- **Destaque no site** — slider de patrocinadores na homepage e/ou página de patrocínios, com logótipo clicável a apontar para o site da empresa

A visibilidade final é acordada diretamente entre a associação e o patrocinador após submissão do formulário.

---

#### 2a. Migrations

**Tabela `sponsors`** — patrocinadores confirmados, exibidos publicamente no site:
```php
Schema::create('sponsors', function (Blueprint $table) {
    $table->id();
    $table->string('empresa');
    $table->string('logotipo')->nullable();       // path da imagem (storage/sponsors/)
    $table->string('website')->nullable();         // URL do site da empresa (com https://)
    $table->string('descricao')->nullable();       // frase curta opcional
    $table->boolean('mostrar_no_slider')->default(true);  // aparece no slider da homepage
    $table->boolean('ativo')->default(true);
    $table->integer('ordem')->default(0);          // ordenação no slider
    $table->timestamps();
});
```

**Tabela `sponsorship_requests`** — formulários submetidos (pedidos de patrocínio):
```php
Schema::create('sponsorship_requests', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('empresa');
    $table->string('email');
    $table->string('telefone')->nullable();
    $table->text('mensagem')->nullable();          // o que a empresa propõe / valor disponível
    $table->boolean('aceita_contacto')->default(false);
    $table->enum('estado', ['pendente', 'contactado', 'confirmado', 'recusado'])->default('pendente');
    $table->timestamps();
});
```

---

#### 2b. Models

**`app/Models/Sponsor.php`**
```php
class Sponsor extends Model
{
    protected $fillable = [
        'empresa', 'logotipo', 'website', 'descricao',
        'mostrar_no_slider', 'ativo', 'ordem'
    ];

    protected $casts = [
        'mostrar_no_slider' => 'boolean',
        'ativo'             => 'boolean',
    ];

    public function getLogoUrlAttribute(): string
    {
        return $this->logotipo
            ? asset('storage/' . $this->logotipo)
            : asset('images/sponsor-placeholder.png');
    }
}
```

**`app/Models/SponsorshipRequest.php`**
```php
class SponsorshipRequest extends Model
{
    protected $fillable = [
        'nome', 'empresa', 'email', 'telefone',
        'mensagem', 'aceita_contacto', 'estado'
    ];
}
```

---

#### 2c. Routes

```php
// Página pública de patrocínios
Route::get('/patrocinios', [SponsorshipController::class, 'index'])->name('patrocinios.index');
Route::post('/patrocinios', [SponsorshipController::class, 'store'])->name('patrocinios.store');
```

---

#### 2d. Controller (`app/Http/Controllers/SponsorshipController.php`)

```php
public function index()
{
    $patrocinadores = Sponsor::where('ativo', true)
        ->orderBy('ordem')
        ->get();

    return view('pages.patrocinios', compact('patrocinadores'));
}

public function store(Request $request)
{
    $validated = $request->validate([
        'nome'          => 'required|string|max:255',
        'empresa'       => 'required|string|max:255',
        'email'         => 'required|email|max:255',
        'telefone'      => 'nullable|string|max:20',
        'mensagem'      => 'nullable|string|max:2000',
        'aceita_contacto' => 'required|accepted',
    ], [
        'nome.required'             => 'O nome é obrigatório.',
        'empresa.required'          => 'O nome da empresa é obrigatório.',
        'email.required'            => 'O email é obrigatório.',
        'email.email'               => 'Introduza um email válido.',
        'aceita_contacto.accepted'  => 'Tem de aceitar ser contactado para submeter a proposta.',
    ]);

    $pedido = SponsorshipRequest::create($validated);

    Mail::to(config('mail.from.address'))->send(new SponsorshipReceived($pedido));
    Mail::to($pedido->email)->send(new SponsorshipConfirmation($pedido));

    return redirect()->route('patrocinios.index')
        ->with('success', 'Obrigado pelo interesse! Entraremos em contacto brevemente para combinar os detalhes.');
}
```

---

#### 2e. Mailables

**`SponsorshipReceived`** — notificação para a associação:
- Nome, empresa, email, telefone do interessado
- Mensagem/proposta enviada
- Link para gestão interna (se existir painel admin)

**`SponsorshipConfirmation`** — confirmação para o patrocinador:
- Agradecimento pelo contacto
- Resumo do que foi enviado
- Indicação de que serão contactados brevemente
- Contacto da associação para dúvidas

Criar com:
```bash
php artisan make:mail SponsorshipReceived --markdown=emails.sponsorship-received
php artisan make:mail SponsorshipConfirmation --markdown=emails.sponsorship-confirmation
```

---

#### 2f. Slider de patrocinadores (homepage + página de patrocínios)

Implementar um slider automático com **Swiper.js** (já disponível via CDN) que exibe os logos dos patrocinadores confirmados, cada um com link clicável para o site da empresa.

**Componente Blade reutilizável** (`resources/views/components/sponsors-slider.blade.php`):

```blade
@if($patrocinadores->isNotEmpty())
<section class="py-10 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4">
        <h2 class="text-center text-2xl font-bold mb-6">Os nossos patrocinadores</h2>
        <div class="swiper sponsors-swiper">
            <div class="swiper-wrapper items-center">
                @foreach($patrocinadores as $sponsor)
                <div class="swiper-slide flex justify-center">
                    <a href="{{ $sponsor->website }}" target="_blank" rel="noopener noreferrer"
                       title="{{ $sponsor->empresa }}" class="block p-4 grayscale hover:grayscale-0 transition">
                        <img src="{{ $sponsor->logo_url }}" alt="{{ $sponsor->empresa }}"
                             class="max-h-20 max-w-[200px] object-contain" loading="lazy">
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
new Swiper('.sponsors-swiper', {
    loop: true,
    autoplay: { delay: 3000, disableOnInteraction: false },
    slidesPerView: 2,
    spaceBetween: 30,
    breakpoints: {
        640:  { slidesPerView: 3 },
        1024: { slidesPerView: 5 },
    }
});
</script>
@endif
```

Incluir na homepage e na página de patrocínios:
```blade
<x-sponsors-slider :patrocinadores="$patrocinadores" />
```

O efeito `grayscale → cor` ao passar o rato dá um toque visual elegante sem ser intrusivo.

---

#### 2g. View da página de patrocínios (`resources/views/pages/patrocinios.blade.php`)

Estrutura:

1. **Hero** — *"Apoia a Festa de Santa Ana"* com subtítulo explicativo
2. **Apresentação da festa** — data, local, público esperado, edições anteriores
3. **Como funciona o patrocínio** — texto explicativo sem valores fixos:
   > *"Cada patrocinador contribui com o que lhe for possível. Em troca, trabalhamos consigo para dar a máxima visibilidade à vossa marca — no recinto com lonas, nas nossas redes sociais e aqui no nosso site."*
4. **Formas de divulgação** — 3 cards ilustrativos:
   - 🪧 **Lona no recinto** — a sua marca visível durante a festa
   - 📱 **Redes sociais** — publicação de agradecimento com menção à empresa
   - 🌐 **Destaque no site** — logo com link para o site da empresa no nosso slider
5. **Formulário de contacto** — campos: nome, empresa, email, telefone, mensagem (proposta livre), checkbox de consentimento
6. **Slider "Os nossos patrocinadores"** — logos dos patrocinadores confirmados com link

---

### 3. Melhorias gerais de design e navegação

- Atualizar o menu de navegação: **Início | Sobre Nós | Eventos | Patrocínios | Contacto**
- Adicionar **CTA na homepage**: botão *"Torna-te patrocinador"* → `/patrocinios`
- Melhorar o rodapé: morada, telefone, email, redes sociais (Facebook, Instagram), links legais
- Garantir **responsividade total** em mobile (verificar breakpoints Tailwind/Bootstrap)
- **Lazy load** em imagens (`loading="lazy"`)
- Animações suaves nas secções com `AOS.js` ou CSS transitions

---

### 4. Páginas Legais (RGPD / Lei n.º 58/2019)

Criar as seguintes páginas estáticas via Blade, em conformidade com o **RGPD** e a legislação portuguesa.

#### Routes
```php
Route::get('/politica-de-privacidade', [LegalController::class, 'privacidade'])->name('legal.privacidade');
Route::get('/termos-e-condicoes', [LegalController::class, 'termos'])->name('legal.termos');
Route::get('/politica-de-cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
```

#### Controller (`app/Http/Controllers/LegalController.php`)
```php
public function privacidade() { return view('legal.privacidade'); }
public function termos()      { return view('legal.termos'); }
public function cookies()     { return view('legal.cookies'); }
```

#### 4a. Política de Privacidade (`resources/views/legal/privacidade.blade.php`)
Deve incluir:
- Identidade e contactos do responsável pelo tratamento (ARDC de Santa Ana, morada, email)
- Dados pessoais recolhidos: formulário de patrocínios, formulário de contacto, cookies
- Finalidade e base legal (consentimento, interesse legítimo)
- Prazo de conservação dos dados (máximo 5 anos para dados de patrocínios)
- Direitos dos titulares: acesso, retificação, apagamento, portabilidade, oposição
- Como exercer direitos: email de contacto da associação
- Direito de reclamação junto da **CNPD** (www.cnpd.pt)
- Referência ao uso de cookies
- Data da última atualização

#### 4b. Termos e Condições (`resources/views/legal/termos.blade.php`)
Deve incluir:
- Identificação da associação e do site
- Condições de acesso e utilização
- Propriedade intelectual (conteúdos, imagens, logótipos da ARDC)
- Limitação de responsabilidade
- Links externos (o site pode conter links para terceiros)
- Lei aplicável: legislação portuguesa / foro dos tribunais competentes
- Reserva do direito de alteração dos termos
- Data da última atualização

#### 4c. Política de Cookies (`resources/views/legal/cookies.blade.php`)
Deve incluir:
- O que são cookies e para que servem
- Tipos de cookies utilizados: essenciais, analíticos (Google Analytics se ativo), de terceiros
- Como gerir ou desativar cookies por browser
- Consentimento (referência ao banner)
- Link para a Política de Privacidade

#### 4d. Banner de consentimento de cookies
Implementar em Blade + JavaScript puro (sem dependências externas):

```blade
{{-- resources/views/components/cookie-banner.blade.php --}}
<div id="cookie-banner" class="fixed bottom-0 w-full bg-gray-900 text-white p-4 z-50 hidden">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
        <p class="text-sm">
            Utilizamos cookies para melhorar a sua experiência. 
            <a href="{{ route('legal.cookies') }}" class="underline">Saiba mais</a>.
        </p>
        <div class="flex gap-3">
            <button onclick="aceitarCookies()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm">Aceitar</button>
            <button onclick="recusarCookies()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded text-sm">Só essenciais</button>
        </div>
    </div>
</div>

<script>
    if (!localStorage.getItem('cookie_consent')) {
        document.getElementById('cookie-banner').classList.remove('hidden');
    }
    function aceitarCookies() {
        localStorage.setItem('cookie_consent', 'all');
        document.getElementById('cookie-banner').classList.add('hidden');
    }
    function recusarCookies() {
        localStorage.setItem('cookie_consent', 'essential');
        document.getElementById('cookie-banner').classList.add('hidden');
    }
</script>
```

Incluir o componente no layout principal: `@include('components.cookie-banner')`

#### 4e. Links legais no rodapé
O rodapé deve incluir:
```blade
<a href="{{ route('legal.privacidade') }}">Política de Privacidade</a>
<a href="{{ route('legal.termos') }}">Termos e Condições</a>
<a href="{{ route('legal.cookies') }}">Política de Cookies</a>
<a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener">Livro de Reclamações</a>
```

---

## Notas adicionais

- Todo o conteúdo textual em **português de Portugal** (usar vírgulas decimais, datas dd/mm/yyyy)
- Validações de formulário com mensagens de erro em português
- Configurar no `.env`: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` com os dados da associação
- Usar `@csrf` em todos os formulários POST
- As páginas legais devem ter uma estrutura HTML limpa para fácil atualização futura
- Testar envio de emails em staging antes de produção (`MAIL_MAILER=log` para testar)
- URL slug da página de patrocínios: `/patrocinios` (sem acentos)

---

## Entregáveis esperados

1. `PagesController` com método `sobreNos` e view correspondente
2. Migrations para `sponsors` e `sponsorship_requests`
3. Models `Sponsor` e `SponsorshipRequest`
4. `SponsorshipController` com `index` e `store` + dois Mailables
5. View `/patrocinios` com apresentação, formas de divulgação, formulário e slider de patrocinadores
6. Componente Blade reutilizável `sponsors-slider` com Swiper.js (logos clicáveis com link para o site da empresa)
7. Homepage com CTA para `/patrocinios` e slider de patrocinadores
5. Menu de navegação atualizado
6. Rodapé melhorado com contactos e links legais
7. `LegalController` com 3 views (privacidade, termos, cookies)
8. Componente de banner de consentimento de cookies
9. Links legais no rodapé incluindo Livro de Reclamações