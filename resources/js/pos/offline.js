/**
 * Posto do bar que trabalha sem internet.
 *
 * Tudo o que o posto faz (vendas, anulações, devoluções de caução) fica
 * primeiro guardado neste computador e só depois é enviado ao servidor, por
 * ordem, sempre que houver rede. Cada registo leva:
 *   - um uuid: se um envio se repetir (a rede caiu a meio), o servidor não o duplica;
 *   - o posto, o ponto, a letra e a caixa em que foi feito: fica lá, mesmo
 *     que seja enviado mais tarde ou com outra sessão.
 *
 * Só um separador do browser pode usar o posto de cada vez (Web Locks): dois
 * separadores apagavam a fila um do outro e repetiam números de senha.
 */
import { reactive } from 'vue';

const VERSAO = 1;
const LOTE = 50;
const INTERVALO_ENVIO = 15000;
const TEMPO_MAXIMO = 15000;
const MAX_VENDAS_GUARDADAS = 400;

const chaveDe = (posId) => `pos-offline-v${VERSAO}:${posId}`;

const novoUuid = () => (crypto.randomUUID ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (crypto.getRandomValues(new Uint8Array(1))[0] & 15);
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    }));

const xsrf = () => {
    const linha = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return linha ? decodeURIComponent(linha.split('=')[1]) : '';
};

export class GuardarFalhou extends Error {}

/** Ha registos deste posto guardados neste computador por enviar? */
export const temPendentes = (posId) => {
    try {
        const guardado = JSON.parse(localStorage.getItem(chaveDe(posId)) || 'null');
        return !!(guardado?.fila?.length || guardado?.recusados?.length);
    } catch {
        return false;
    }
};

// Uma so instancia por posto nesta pagina: o Inertia pode montar o ecra de
// novo (depois de uma acao) e duas instancias escreviam por cima uma da outra.
const instancias = new Map();

export function criarPostoOffline({ posId, urlDados, urlEnviar }) {
    if (instancias.has(posId)) return instancias.get(posId);

    const chave = chaveDe(posId);
    let relogio = null;

    const estado = reactive({
        online: typeof navigator === 'undefined' ? true : navigator.onLine,
        aEnviar: false,
        sessaoExpirada: false,
        // Outro separador ja esta a usar este posto: este nao vende
        bloqueado: false,
        ultimoEnvio: null,
        ultimoErro: '',
        // Guardado no computador
        dados: null,      // resposta de /pos/offline/dados
        fila: [],         // registos por enviar, por ordem
        vendas: [],       // vendas recentes (para reimprimir/anular)
        recusados: [],    // registos que o servidor recusou de vez (para a comissao ver)
        numeros: {},      // ultimo numero de senha por caixa
        stock: {},        // stock local por produto
    });

    // ---- Guardar / ler -------------------------------------------------------
    const guardar = () => {
        // Separador sem o trinco: nunca escreve (apagava a fila do separador que esta a vender)
        if (estado.bloqueado) return;
        const { dados, fila, vendas, recusados, numeros, stock } = estado;
        try {
            localStorage.setItem(chave, JSON.stringify({ dados, fila, vendas, recusados, numeros, stock }));
        } catch (e) {
            throw new GuardarFalhou('Não foi possível guardar neste computador (memória cheia ou bloqueada).');
        }
    };

    const ler = () => {
        try {
            const guardado = JSON.parse(localStorage.getItem(chave) || 'null');
            if (guardado) Object.assign(estado, guardado);
        } catch { /* sem dados guardados */ }
    };

    // ---- Dados do servidor ---------------------------------------------------
    const caixaId = () => estado.dados?.caixa?.id ?? null;

    // O servidor ainda nao descontou o que esta na fila: o stock local e o do
    // servidor menos o que este posto vendeu e ainda nao enviou.
    const recalcularStock = () => {
        const pendente = {};
        const naFila = new Set(estado.fila.filter((e) => e.tipo === 'venda').map((e) => e.uuid));
        for (const e of estado.fila) {
            if (e.tipo === 'venda') {
                const anulada = estado.fila.some((a) => a.tipo === 'anulacao' && a.venda_uuid === e.uuid);
                if (anulada) continue;
                for (const i of e.items) pendente[i.produto_id] = (pendente[i.produto_id] ?? 0) + Number(i.quantidade);
            } else if (e.tipo === 'anulacao' && !naFila.has(e.venda_uuid)) {
                // Venda ja enviada (o servidor ja a descontou) e anulacao ainda por enviar: volta ao stock
                const venda = estado.vendas.find((v) => v.uuid === e.venda_uuid);
                for (const i of venda?.items ?? []) pendente[i.produto_id] = (pendente[i.produto_id] ?? 0) - Number(i.quantidade);
            }
        }
        const stock = {};
        for (const p of estado.dados?.produtos ?? []) {
            if (p.gerir_stock) stock[p.id] = Math.max(0, Number(p.stock_atual) - (pendente[p.id] ?? 0));
        }
        estado.stock = stock;
    };

    const pedir = async (url, opcoes = {}) => {
        // Hotspot ligado mas sem internet: o pedido fica pendurado; desiste ao fim de 15 s
        const controlo = new AbortController();
        const limite = setTimeout(() => controlo.abort(), TEMPO_MAXIMO);
        try {
            return await fetch(url, {
                credentials: 'same-origin',
                ...opcoes,
                signal: controlo.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(opcoes.headers ?? {}) },
            });
        } catch {
            estado.online = false;
            throw new Error('Sem ligação ao servidor.');
        } finally {
            clearTimeout(limite);
        }
    };

    const pedirJson = async (url, opcoes = {}, { tentarSessao = true } = {}) => {
        const res = await pedir(url, opcoes);
        estado.online = true;
        const json = (res.headers.get('content-type') ?? '').includes('application/json');

        // Sessao/CSRF expirados (horas sem rede): um GET recupera a sessao do posto e o token; tenta outra vez
        if ((res.status === 419 || res.status === 401) && tentarSessao) {
            await pedir(urlDados).catch(() => {});
            return pedirJson(url, { ...opcoes, headers: { ...(opcoes.headers ?? {}), 'X-XSRF-TOKEN': xsrf() } }, { tentarSessao: false });
        }
        if (res.status === 401 || res.status === 419 || res.redirected || (res.ok && !json)) {
            estado.sessaoExpirada = true;
            throw new Error('A sessão do POS expirou. Recarrega a página com internet e volta a entrar se for preciso — as vendas guardadas não se perdem.');
        }
        if (!res.ok) throw new Error(`O servidor respondeu ${res.status}.`);
        estado.sessaoExpirada = false;
        return res.json();
    };

    const atualizarDados = async () => {
        const dados = await pedirJson(urlDados);
        if (dados.posto?.id !== posId) {
            // A sessao e de outro posto: nao mistura os dados deste
            throw new Error('Este computador entrou com outro posto. Volta a entrar neste posto para enviar o que falta.');
        }
        const caixaAntes = caixaId();
        estado.dados = dados;
        const id = dados.caixa?.id;
        if (id) {
            // Nunca repetir numeros: o maior entre o que este posto ja deu e o que o servidor recebeu
            estado.numeros = { ...estado.numeros, [id]: Math.max(Number(estado.numeros[id] ?? 0), Number(dados.ultimo_numero ?? 0)) };
        }
        // Caixa nova: as vendas da anterior que ja foram enviadas deixam de interessar
        if (caixaAntes && id && caixaAntes !== id) {
            estado.vendas = estado.vendas.filter((v) => !v.enviada);
        }
        recalcularStock();
        guardar();
        return dados;
    };

    // ---- Envio ---------------------------------------------------------------
    const enviar = async () => {
        if (estado.aEnviar || !estado.fila.length || !navigator.onLine || estado.bloqueado) return;
        estado.aEnviar = true;
        estado.ultimoErro = '';

        try {
            while (estado.fila.length) {
                const lote = estado.fila.slice(0, LOTE);
                const { resultados } = await pedirJson(urlEnviar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() },
                    body: JSON.stringify({ eventos: lote, enviado_em: new Date().toISOString() }),
                });

                const porUuid = new Map(resultados.map((r) => [r.uuid, r]));
                const vendasRecusadas = new Set();
                let avancou = false;
                const fila = [];

                for (const evento of estado.fila) {
                    const r = porUuid.get(evento.uuid);
                    // Anulacao de uma venda recusada de vez (agora ou antes): sai com ela
                    if (evento.tipo === 'anulacao' && (vendasRecusadas.has(evento.venda_uuid) || estado.recusados.some((x) => x.tipo === 'venda' && x.uuid === evento.venda_uuid))) {
                        estado.recusados.push({ ...evento, erro: 'A venda foi recusada.', recusado_em: new Date().toISOString() });
                        continue;
                    }
                    if (!r) { fila.push(evento); continue; }
                    if (r.ok) {
                        avancou = true;
                        if (evento.tipo === 'venda') {
                            const venda = estado.vendas.find((v) => v.uuid === evento.uuid);
                            if (venda) { venda.enviada = true; venda.servidor_id = r.id; }
                        }
                    } else if (r.definitivo) {
                        // Dados impossiveis: sai da fila para nao travar o resto, mas fica a vista
                        avancou = true;
                        if (evento.tipo === 'venda') vendasRecusadas.add(evento.uuid);
                        estado.recusados.push({ ...evento, erro: r.erro, recusado_em: new Date().toISOString() });
                    } else {
                        fila.push(evento);
                        estado.ultimoErro = r.erro;
                    }
                }

                estado.fila = fila;
                guardar();
                if (!avancou) break;
            }
            estado.ultimoEnvio = new Date().toISOString();
            await atualizarDados();
        } catch (e) {
            estado.ultimoErro = e?.message || String(e);
        } finally {
            estado.aEnviar = false;
        }
    };

    /** A comissao pode voltar a tentar enviar os recusados (ex.: depois de corrigir um produto). */
    const repetirRecusados = () => {
        if (estado.bloqueado) return;
        estado.fila = [...estado.recusados.map(({ erro, recusado_em, ...evento }) => evento), ...estado.fila];
        estado.recusados = [];
        guardar();
        enviar();
    };

    // ---- Registo local -------------------------------------------------------
    const origem = () => ({
        pos_id: posId,
        ponto: estado.dados?.posto?.ponto,
        caixa_id: caixaId(),
    });

    const juntarFila = (evento, alterar) => {
        const copia = JSON.parse(JSON.stringify({ fila: estado.fila, vendas: estado.vendas, numeros: estado.numeros, stock: estado.stock }));
        try {
            estado.fila.push(evento);
            alterar?.();
            if (estado.vendas.length > MAX_VENDAS_GUARDADAS) estado.vendas = estado.vendas.slice(0, MAX_VENDAS_GUARDADAS);
            guardar();
        } catch (e) {
            // Nao ficou guardado: desfaz tudo, para nao imprimir uma venda que se perde
            Object.assign(estado, copia);
            throw e;
        }
        setTimeout(enviar, 0);
    };

    const verificarPodeVender = () => {
        if (estado.bloqueado) throw new Error('Este posto já está aberto noutro separador. Usa só um.');
        if (!caixaId()) throw new Error('A caixa deste ponto não está aberta.');
        if (estado.dados?.posto?.id !== posId) throw new Error('Os dados guardados são de outro posto. Liga à internet e volta a entrar.');
    };

    /** Regista a venda e devolve-a com o numero de senha. Lanca GuardarFalhou se nao ficar guardada. */
    const registarVenda = (dadosVenda) => {
        verificarPodeVender();
        const id = caixaId();
        const numero = Number(estado.numeros[id] ?? 0) + 1;
        const prefixo = estado.dados?.posto?.prefixo ?? '';
        const venda = {
            ...dadosVenda,
            ...origem(),
            tipo: 'venda',
            uuid: novoUuid(),
            numero,
            prefixo,
            codigo: prefixo ? `${prefixo}-${numero}` : String(numero),
            criado_em: new Date().toISOString(),
        };
        juntarFila(venda, () => {
            estado.numeros = { ...estado.numeros, [id]: numero };
            estado.vendas.unshift({ ...venda, anulada: false, enviada: false });
            for (const i of venda.items) {
                if (i.produto_id in estado.stock) estado.stock[i.produto_id] = Math.max(0, estado.stock[i.produto_id] - Number(i.quantidade));
            }
        });
        return venda;
    };

    const registarAnulacao = (venda, { motivo, operador }) => {
        if (estado.bloqueado) throw new Error('Este posto já está aberto noutro separador. Usa só um.');
        const evento = { ...origem(), tipo: 'anulacao', uuid: novoUuid(), venda_uuid: venda.uuid, motivo, operador, criado_em: new Date().toISOString() };
        juntarFila(evento, () => {
            const local = estado.vendas.find((v) => v.uuid === venda.uuid);
            if (local) Object.assign(local, { anulada: true, anulada_em: evento.criado_em, motivo, anulada_por: operador });
            for (const i of venda.items) {
                if (i.produto_id in estado.stock) estado.stock[i.produto_id] += Number(i.quantidade);
            }
        });
        return evento;
    };

    /** Anulacao feita no servidor (mais de 5 minutos): so marca a venda local. */
    const marcarAnulada = (vendaUuid, dados) => {
        const local = estado.vendas.find((v) => v.uuid === vendaUuid);
        if (local) Object.assign(local, { anulada: true, ...dados });
        try { guardar(); } catch { /* fica so em memoria; o servidor ja tem a anulacao */ }
    };

    const registarCaucao = ({ produto, quantidade, operador }) => {
        verificarPodeVender();
        const evento = { ...origem(), tipo: 'caucao', uuid: novoUuid(), produto_id: produto.id, quantidade, caucao: Number(produto.caucao), operador, criado_em: new Date().toISOString() };
        juntarFila(evento);
        return evento;
    };

    // ---- Arranque ------------------------------------------------------------
    const aoMudarRede = () => {
        if (!navigator.onLine) { estado.online = false; return; }
        if (estado.fila.length) enviar();
        else atualizarDados().catch(() => {});
    };

    /** Fica com o posto so para este separador; se outro ja o tem, este fica bloqueado. */
    const pedirTrinco = () => new Promise((resolve) => {
        if (!navigator.locks?.request) { resolve(true); return; }
        navigator.locks.request(`pos-offline-${posId}`, { ifAvailable: true }, (trinco) => {
            if (!trinco) { resolve(false); return undefined; }
            resolve(true);
            // Fica com o trinco ate o separador fechar
            return new Promise(() => {});
        }).catch(() => resolve(true));
    });

    // Uma vez por pagina: o posto fica ativo enquanto a pagina estiver aberta,
    // mesmo que o ecra seja montado de novo (e o trinco so sai ao fechar o separador).
    let iniciado = null;
    const iniciar = ({ soEnviar = false } = {}) => {
        iniciado ??= (async () => {
            ler();
            estado.bloqueado = !(await pedirTrinco());
            if (estado.bloqueado) return;

            navigator.storage?.persist?.().catch(() => {});
            window.addEventListener('online', aoMudarRede);
            window.addEventListener('offline', aoMudarRede);
            relogio = setInterval(() => {
                if (!navigator.onLine) { estado.online = false; return; }
                if (estado.fila.length) enviar();
                else if (!estado.aEnviar && !soEnviar) atualizarDados().catch(() => {});
            }, INTERVALO_ENVIO);

            if (navigator.onLine) {
                // Primeiro envia o que ficou (o numero da caixa nova so se sabe depois)
                await enviar();
                if (!soEnviar) {
                    try {
                        await atualizarDados();
                    } catch (e) {
                        estado.ultimoErro = e?.message || String(e);
                    }
                }
            }
        })();
        return iniciado;
    };

    // O ecra saiu, mas o posto continua a enviar o que tiver enquanto a pagina estiver aberta
    const parar = () => {};

    const posto = { estado, iniciar, parar, enviar, atualizarDados, registarVenda, registarAnulacao, marcarAnulada, registarCaucao, repetirRecusados, recalcularStock };
    instancias.set(posId, posto);
    return posto;
}
