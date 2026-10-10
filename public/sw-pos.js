/*
 * Service worker do POS do bar que trabalha sem internet.
 *
 * Guarda o ecra do POS (/pos) e os ficheiros do site (/build, /storage) para o
 * POS abrir mesmo sem rede. So mexe nestes caminhos: o resto do site (o
 * backoffice) funciona como sempre. As vendas sem rede ficam no proprio POS
 * (resources/js/pos/offline.js), nao aqui.
 */
const CACHE = 'pos-offline-v1';
const TEMPO_REDE = 5000;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

// O POS manda a lista dos ficheiros que esta a usar: guardam-se e os antigos saem
self.addEventListener('message', (e) => {
    if (e.data?.tipo !== 'guardar') return;
    const urls = (e.data.urls ?? []).filter((u) => new URL(u, self.location.origin).origin === self.location.origin);
    e.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await Promise.all(urls.map((u) => cache.add(new Request(u, { credentials: 'same-origin' })).catch(() => {})));
        const manter = new Set(urls.map((u) => new URL(u, self.location.origin).href));
        for (const pedido of await cache.keys()) {
            if (new URL(pedido.url).pathname.startsWith('/build/') && !manter.has(pedido.url)) await cache.delete(pedido);
        }
    })());
});

const comLimite = (promessa, ms) => Promise.race([promessa, new Promise((_, falhar) => setTimeout(() => falhar(new Error('tempo')), ms))]);

const guardavel = (res) => res && res.ok && !res.redirected && res.type === 'basic';

self.addEventListener('fetch', (e) => {
    const pedido = e.request;
    if (pedido.method !== 'GET') return;
    const url = new URL(pedido.url);
    if (url.origin !== self.location.origin) return;

    // Ecra do POS: rede primeiro (dados frescos); sem rede, o que ficou guardado
    if (pedido.mode === 'navigate' && url.pathname === '/pos') {
        e.respondWith((async () => {
            const cache = await caches.open(CACHE);
            try {
                const res = await comLimite(fetch(pedido), TEMPO_REDE);
                if (guardavel(res)) await cache.put('/pos', res.clone());
                return res;
            } catch {
                return (await cache.match('/pos')) ?? new Response('<h1>Sem internet</h1><p>Este posto ainda nao foi aberto com internet. Liga-o uma vez para guardar o POS.</p>', { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 });
            }
        })());
        return;
    }

    // Ficheiros do build: nomes com hash, nunca mudam — o guardado serve
    if (url.pathname.startsWith('/build/')) {
        e.respondWith((async () => {
            const cache = await caches.open(CACHE);
            const guardado = await cache.match(pedido, { ignoreSearch: true });
            if (guardado) return guardado;
            const res = await fetch(pedido);
            if (guardavel(res)) await cache.put(pedido, res.clone());
            return res;
        })());
        return;
    }

    // Imagens dos produtos: podem ser trocadas no backoffice — rede primeiro, guardado se nao houver
    if (url.pathname.startsWith('/storage/')) {
        e.respondWith((async () => {
            const cache = await caches.open(CACHE);
            try {
                const res = await comLimite(fetch(pedido), TEMPO_REDE);
                if (guardavel(res)) await cache.put(pedido, res.clone());
                return res;
            } catch {
                return (await cache.match(pedido)) ?? Response.error();
            }
        })());
    }
});
