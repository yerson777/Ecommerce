const CACHE_NAME = 'everly-v1';

// Instalar: no cachear nada, solo tomar control inmediato
self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

// Activar: tomar control de las páginas inmediatamente
self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Fetch: NO cachear nada, dejar pasar todas las peticiones a la red
self.addEventListener('fetch', (event) => {
  // No interceptor nada: dejar que el navegador maneje la petición normalmente
  return;
});
