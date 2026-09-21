# Impressao dos taloes

Ha duas montagens suportadas, e nenhuma delas leva software instalado nos
computadores dos postos.

## 1. Impressora de rede, com o Raspberry Pi a imprimir

A impressora tem IP fixo e porta ESC/POS (`9100`). O Raspberry corre este
agente, vai buscar os trabalhos pendentes pela API e imprime-os. E o unico
equipamento com software instalado.

No backoffice, a impressora fica com o tipo **Rede** e o campo *Posto (agente)*
com o nome do Raspberry (o mesmo `AGENTE` do `.env` dele).

Instalacao no Raspberry: `setup-pi.sh` (instala Node, configura o agente e
ativa o servico automatico).

Uma impressora ligada por USB **ao proprio Raspberry** tambem funciona: tipo
**USB no Raspberry**, com o dispositivo (normalmente `/dev/usb/lp0`).

## 2. Impressora USB no posto, impressa pelo browser (WebUSB)

Sem agente nenhum. O browser envia os bytes ESC/POS directamente para a
impressora USB daquele equipamento — corte, alinhamento, senha grande e gaveta,
tudo igual ao agente, porque e o mesmo codigo (`resources/js/escpos.js`).

No backoffice, a impressora fica com o tipo **USB pelo browser (WebUSB)** e o
posto POS aponta para ela. Em cada equipamento autoriza-se a impressora uma
vez; depois reconecta sozinho.

- **Chromebook:** e o caminho indicado. A impressora nao pode estar adicionada
  nas definicoes de impressao do ChromeOS — se estiver, o sistema fica com ela
  e o browser nao a consegue abrir.
- **Windows:** o driver de impressora do sistema reclama o dispositivo e o
  WebUSB nao lhe chega. Nesses postos usa-se a montagem 1: impressora na rede,
  Raspberry a imprimir.

Para testar antes do evento: **Impressoras > Teste USB (WebUSB)**.

## O que NAO se usa

Imprimir a pagina do talao pelo navegador (HTML) nao serve em impressoras
termicas: vai pelo driver, que usa papel de tamanho fixo e nao envia o comando
de corte (`0x1D 0x56 0x00`). Sai desalinhado e nao corta. Fica disponivel como
tipo **Impressao normal do browser** apenas para impressoras comuns.

## Agente em Windows (em vez do Raspberry)

A pasta `windows/` corre este mesmo agente num PC Windows (Node.js 18+):

1. `instalar-agente.bat` — cria o `.env` (site, token, nome do posto).
2. `testar-impressora.bat` — pede o IP e imprime um talao de teste directo
   na impressora (porta 9100), sem passar pelo site. Se falhar aqui, o
   problema e de rede entre o PC e a impressora, nao do site.
3. `iniciar-agente.bat` — poe o agente a trabalhar (reinicia se parar).
4. `arranque-automatico.bat` (como administrador) — arranca ao iniciar sessao.

No backoffice, a impressora fica com tipo **Rede**, IP + porta 9100 e
*Posto (agente)* igual ao `AGENTE` do `.env`. A janela do agente diz, ao
arrancar, que posto esta a tratar, e de minuto a minuto avisa se nao ha
trabalhos para esse posto.

## Varios agentes

Se houver mais do que um agente, da a cada um o seu `AGENTE` no `.env` e poe
esse mesmo nome no campo *Posto (agente)* das impressoras que ele trata. Sem
isso, cada agente tenta imprimir taloes de impressoras que nao tem.

## Postos

Em *Impressoras > Postos POS*, cada posto aponta para a sua impressora. Sem
isso, o talao sai na primeira impressora da seccao — com varios postos, quase
sempre a errada.
