import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { App } from './app/app';
import { environment } from './environments/environment';

bootstrapApplication(App, appConfig)
  .catch((err) => console.error(err));

// PWA: service worker solo en producción y en contexto seguro (HTTPS o localhost).
const contextoSeguro =
  location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname);

if (environment.production && contextoSeguro && 'serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}
