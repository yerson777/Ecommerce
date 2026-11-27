// Service worker mínimo: NO cachea nada (solo permite instalar la PWA).
// Ninguna petición se guarda: todo va a la red por defecto.
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Sin respondWith: no se cachea nada, la petición va a la red por defecto.
});
