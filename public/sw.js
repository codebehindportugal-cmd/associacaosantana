// Service Worker — Associação de Santana
// Interceta push notifications e mostra-as ao utilizador

self.addEventListener('push', function (event) {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Associação de Santana', body: event.data ? event.data.text() : 'Notificação' };
    }

    const title = data.title || 'Associação de Santana';
    const options = {
        body:   data.body  || 'A sua vez chegou!',
        icon:   data.icon  || '/favicon.ico',
        badge:  data.badge || '/favicon.ico',
        tag:    data.tag   || 'reserva',
        silent: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    // Notificações de "sentada" abrem o ecrã de reservas para o cliente ver a mesa
    const url = event.notification.tag && event.notification.tag.startsWith('sentada-')
        ? '/ecra-reservas'
        : '/';
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        for (const client of clientList) {
            if (client.url.includes(url) && 'focus' in client) {
                return client.focus();
            }
        }
        if (clients.openWindow) {
            return clients.openWindow(url);
        }
    }));
});

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(clients.claim()));
