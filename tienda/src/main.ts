import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { App } from './app/app';

bootstrapApplication(App, appConfig)
  .catch((err) => console.error(err));

// Registrar service worker de PWA (sin caché, solo para instalabilidad)
if ('serviceWorker' in navigator) {
  navigator.serviceWorker
    .register('/sw.js')
    .catch(() => {});
}
