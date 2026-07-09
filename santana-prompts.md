O redesign anterior ficou demasiado simples e sem impacto visual. Quero que avances de novo, mas desta vez sê muito mais arrojado — o objetivo é um site que "chama a atenção" de imediato, não um refresh discreto. Lê isto com atenção antes de tocar em código.

CONTEXTO
Já analisaste o layout, CSS e componentes do projeto anteriormente (Laravel/Blade). Não precisas de repetir essa auditoria a não ser que seja útil para esta nova passagem. O problema da tentativa anterior foi: mudou pouco, ficou parecido com o estilo antigo, sem impacto. Esta tentativa tem de ser visivelmente diferente e mais arrojada.

O QUE QUERO DESTA VEZ — combinação de 3 elementos:

1. HERO GRANDE E VISUAL
- Secção inicial (hero) que ocupa boa parte do ecrã (ex: 80-100vh), com imagem de fundo de impacto (paisagem/caça/natureza relacionada com a ARDC, ou um gradiente forte e moderno se não houver imagem adequada), overlay para garantir legibilidade do texto.
- Título grande, tipografia com presença (peso forte, tamanho generoso — pensa em 3.5rem+ em desktop).
- Pode incluir um CTA visível (botão com destaque) e/ou indicador de scroll.

2. CORES FORTES E IDENTIDADE MARCANTE
- Não tenhas medo de usar uma cor de destaque forte e saturada (não pastel, não cinza-sobre-cinza) — escolhe tu a paleta, mas tem de ter contraste real e personalidade, não "site institucional seguro".
- Define uma paleta clara: 1 cor primária forte, 1 cor secundária/acento, neutros de apoio (não precisam de ser só branco/cinza — considera um tom escuro como base em secções alternadas para dar ritmo visual).
- Alterna secções com fundos diferentes (claro/escuro, ou cor sólida) para criar ritmo ao longo da página, em vez de tudo branco do topo ao fundo.

3. ANIMAÇÕES E MICRO-INTERAÇÕES
- Animações de entrada ao fazer scroll (fade-in / slide-up nos elementos conforme entram no viewport) — usa CSS puro (Intersection Observer + transitions/keyframes) ou uma lib leve via CDN se ajudar (ex: AOS - Animate On Scroll), sem dependências pesadas.
- Hover states notórios em botões, cards e links (não só mudar opacidade — pensa em transform/scale, mudança de cor, sombras dinâmicas).
- Transições suaves em todos os elementos interativos (transition em vez de mudanças abruptas).
- Considera algum elemento de destaque extra: parallax leve no hero, contador animado de números (ex: nº de sócios/anos), ou cards com efeito de profundidade ao passar o rato.

IMPORTANTE — NÃO REPETIR O ERRO ANTERIOR
- Não te limites a ajustar variáveis de cor e espaçamento. Quero mudanças estruturais visíveis: hero novo, secções com fundos alternados, elementos que se movem/reagem.
- Se em dúvida entre uma opção "segura" e uma mais arrojada, escolhe a arrojada.
- O resultado final deve ser claramente diferente do site atual ao primeiro olhar, não um polish subtil.

IMPLEMENTAÇÃO
- Aplica isto de forma centralizada (tema/variáveis CSS, componentes partilhados) para propagar a todas as páginas.
- Mantém responsividade total — o hero, as animações e os efeitos de hover têm de funcionar bem em mobile também (ajusta ou simplifica animações em mobile se necessário por performance).
- Não alteres lógica de backend, rotas ou funcionalidades — isto é só visual (estrutura Blade quando necessário, CSS/SCSS, JS leve para animações).
- Mantém a identidade da ARDC de Santa Ana como associação local/comunitária — arrojado não significa frio ou corporativo, pode ter calor e identidade visual forte ao mesmo tempo.

No final, mostra-me um resumo do que foi alterado, em que ficheiros, e quais as decisões de design tomadas (paleta escolhida, fontes, efeitos aplicados) para eu poder rever antes do deploy.