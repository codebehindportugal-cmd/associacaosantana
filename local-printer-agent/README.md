# Agente Local de Impressao

Este agente corre num PC da associacao, dentro da mesma rede das impressoras.
O Laravel fica online e o agente puxa os trabalhos pendentes pela API.

## Configuracao

1. No servidor Laravel, definir:

```env
PRINT_AGENT_TOKEN=um-token-longo-e-seguro
```

2. No PC local, criar `.env` a partir de `.env.example`.

3. Iniciar:

```bash
node --env-file=.env agent.mjs
```

As impressoras devem ser de rede, com IP fixo e porta ESC/POS normalmente `9100`.

No backoffice, em `Impressoras`, cria uma impressora por destino e escolhe a
seccao correspondente. Para separar a cozinha, usa por exemplo `Frango`,
`Acompanhamentos`, `Bebidas` e `Contas`, cada uma com o seu `Host/IP` e `Porta`.
