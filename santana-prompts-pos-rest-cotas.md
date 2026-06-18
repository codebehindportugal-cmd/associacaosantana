Quero modernizar o visual de todo o site (projeto Laravel/Blade) para um estilo mais clean e moderno. Antes de tocares em código, faz o seguinte:

1. Analisa a estrutura visual atual do projeto: layout principal (ex: resources/views/layouts/app.blade.php ou equivalente), ficheiros CSS/SCSS (resources/css, public/css), e os componentes/partials reutilizados em várias páginas (header, footer, navbar, cards, botões).
2. Identifica a paleta de cores atual, tipografia (fontes usadas), espaçamentos e padrões de componentes (cards, botões, secções) que já existem.
3. Faz um resumo curto do que encontraste e do que está a tornar o visual "datado" (ex: sombras pesadas, gradientes antigos, tipografia genérica, falta de espaço em branco, inconsistência de cores, etc.).

Depois do resumo, propõe um novo direcionamento visual "clean e moderno":
- Pode alterar a paleta de cores atual se isso ajudar a atingir um visual mais moderno, mas deve manter coerência com a identidade da ARDC de Santa Ana (associação local/comunitária — evitar algo demasiado corporativo ou frio).
- Tipografia moderna (sugere 1-2 fontes via Google Fonts, uma para títulos e outra para texto, se fizer sentido).
- Mais espaço em branco, hierarquia visual clara, cantos arredondados consistentes, sombras suaves (não pesadas), grid bem organizado.
- Botões, cards e secções com um estilo consistente em todo o site.
- Mobile-first / totalmente responsivo.

Implementação:
- Aplica as alterações de forma centralizada (variáveis CSS / ficheiro de tema) para que o estilo se propague a todas as páginas sem teres de editar cada Blade individualmente, sempre que possível.
- Atualiza o layout principal, header, footer e componentes partilhados primeiro.
- Depois percorre as páginas principais (Home, Sobre Nós, e outras existentes) e ajusta o que for necessário para ficar consistente com o novo estilo.
- Não alteres lógica de backend, rotas ou funcionalidades — isto é apenas uma intervenção visual (HTML/Blade estrutura quando necessário, CSS/SCSS, e classes).
- Mantém o código organizado e comentado onde fizer sentido.

No final, mostra-me um resumo do que foi alterado e em que ficheiros, para eu poder rever antes de dar deploy.