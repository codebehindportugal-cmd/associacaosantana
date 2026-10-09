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
9. **Ensaio:** no Laragon, correr `testar-evento.bat` (testes dos 3 postos de
   pré-pagamento: senhas sem repetidos, impressão por posto, cauções, caixa e
   agente). Só fazer deploy com tudo a verde.
10. **Raspberry:** depois do deploy, em *Impressoras → Agente de impressão*:
    - o estado tem de dizer **Agente ligado** (se disser token errado, colar o
      token do Raspberry em "Usar um token que já tens");
    - copiar o comando **Atualizar o programa do agente** e colá-lo no terminal
      do Raspberry (a versão nova não deixa uma impressora parada atrasar as
      outras).
11. **Teste real em cada posto:** uma venda com 2 imperiais e 1 bifana — têm de
    sair 3 senhas e a conta no fim, na impressora desse posto, e a gaveta abrir.

## No ChromeOS

A impressora **não pode** estar adicionada nas definições de impressão do
sistema. Se lá estiver, o ChromeOS fica com ela e o browser não a abre.

## Durante o evento

- **Senhas:** a contagem é uma só para os 3 postos de pré-pagamento (e para o
  pré-pago do backoffice) — dois postos nunca dão o mesmo número. Recomeça no 1
  ao abrir a **primeira** caixa do dia, e só se não houve senhas nas últimas
  6 horas: fechar as caixas todas a meio da noite (troca de turno) e voltar a
  abrir continua a contagem.
- **Juntar senhas:** por omissão sai uma senha por unidade. Se o cliente pedir,
  no POS toca-se em *Comida* (inclui frango e acompanhamentos), *Sobremesas* ou
  *Bebidas* para essa secção sair numa só folha. A conta sai sempre no fim.
- **Metro/caução:** *Novo* cobra a caução; *Encher* é para quem já traz o metro
  (sem caução). Metro devolvido: *Devolução de caução* → em bebidas ou dinheiro.
- **Senhas anteriores** (botão no topo do POS, ou tocar numa das últimas
  senhas): ver o que foi pedido e pago. *Reimprimir tudo* ou *Só a conta* sai
  com "2a VIA" e não abre a gaveta. *Anular senha* pede o motivo, mostra quanto
  devolver ao cliente, tira a senha das vendas e da caixa, cancela os talões que
  ainda não saíram e imprime um talão "ANULADA" (abre a gaveta se houver
  dinheiro a devolver). Só se anulam senhas do próprio posto e do próprio dia.
- **Impressora sem papel/desligada:** as outras continuam a imprimir. Os talões
  dessa ficam em espera e saem quando ela voltar (até 10 minutos; depois disso
  já não saem — reimprimir em *Senhas anteriores*).
- **Pagamento:** no POS escolhe-se *Dinheiro*, *MB WAY* ou *Contactless*
  antes de cobrar. Só o dinheiro tem troco e abre a gaveta. Volta sempre a
  *Dinheiro* depois de cada venda.
- **Caixa:** "Na gaveta" é só o dinheiro (fundo + vendas a dinheiro + troco
  deixado + cauções, menos cauções devolvidas). MB WAY e Contactless aparecem à
  parte, para conferir no terminal / telemóvel.
- Para acrescentar um posto num pico de afluência: *Impressoras → Postos POS →
  Novo posto*. Se for um telemóvel, não tem impressora própria — o talão sai
  onde estiver a impressora que lhe atribuíres.
- Divisão da receita: percentagem igual para todas, sobre a **receita bruta**.
  Cada associação suporta os seus custos.

## Depois

- Fechar as caixas de cada ponto.
- Conferir **Contas da Festa** e a divisão por associação.
- Em *Impressoras*, confirmar que não ficaram trabalhos falhados por imprimir.
