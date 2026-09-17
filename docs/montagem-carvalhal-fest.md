# Montagem — Carvalhal Fest (9, 10 e 11 de outubro de 2026)

Evento partilhado com as outras coletividades da freguesia. Tasquinhas nos três
dias, caminhada no domingo. Pré-pagamento: o cliente paga tudo no posto e leva
talões para levantar em cada tasquinha.

## Arquitetura

- **Um POS por posto, uma impressora por POS.** Não há impressoras de cozinha
  nem de secção: o talão do cliente é o único papel que chega à tasquinha.
- **Chromebooks:** impressora USB, tipo *USB pelo browser (WebUSB)*. Sem agente.
- **Postos Windows:** o WebUSB não abre impressoras em Windows. Aí a impressora
  vai para a rede, com IP fixo, e o Raspberry imprime (tipo *Rede*).

## Antes do evento

1. `php artisan migrate` e `npm run build`.
2. **Restaurante → Talão:** criar o modelo do Carvalhal Fest, associá-lo ao
   evento, ligar **"Evento com pré-pagamento — só saem talões individuais"** e
   carregar em **Usar este**.
   - Conferir o cabeçalho, o rodapé e as instruções do talão individual.
   - As linhas não podem passar dos 42 caracteres (a página avisa).
3. **Restaurante → Talão → Produtos com talão individual:** marcar a caminhada,
   o frango e tudo o que o cliente leva em talão próprio.
4. **Produtos:** criar a caminhada com preço, disponível no bar.
5. **Impressoras:** criar uma impressora por posto, com o tipo certo.
6. **Impressoras → Postos POS:** criar os postos com PIN e apontar cada um à sua
   impressora. Confirmar que não há avisos a vermelho nem a amarelo.
7. **Impressoras → Teste USB (WebUSB):** em cada Chromebook, autorizar a
   impressora e imprimir os dois talões de teste. Confirmar que **corta** e que
   sai alinhado.
8. **Contas da Festa:** acrescentar as associações participantes e carregar em
   **Igualar percentagens**. Gerar o **link partilhado** e distribuí-lo.

## No ChromeOS

A impressora **não pode** estar adicionada nas definições de impressão do
sistema. Se lá estiver, o ChromeOS fica com ela e o browser não a abre.

## Durante o evento

- As senhas recomeçam no 1 quando se abre a **primeira** caixa de bar do dia.
  Abrir a segunda caixa a meio da noite não mexe na contagem.
- Para acrescentar um posto num pico de afluência: *Impressoras → Postos POS →
  Novo posto*. Se for um telemóvel, não tem impressora própria — o talão sai
  onde estiver a impressora que lhe atribuíres.
- Divisão da receita: percentagem igual para todas, sobre a **receita bruta**.
  Cada associação suporta os seus custos.

## Depois

- Fechar as caixas de cada ponto.
- Conferir **Contas da Festa** e a divisão por associação.
- Em *Impressoras*, confirmar que não ficaram trabalhos falhados por imprimir.
