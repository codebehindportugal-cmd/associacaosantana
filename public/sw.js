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
    event.waitUntil(clients.matchAll({ type: 'window' }).then(function (clientList) {
        for (const client of clientList) {
            if ('focus' in client) {
                return client.focus();
            }
        }
        if (clients.openWindow) {
            return clients.openWindow('/');
        }
    }));
});

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(clients.claim()));
