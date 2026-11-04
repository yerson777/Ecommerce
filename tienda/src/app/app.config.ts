import {
  ApplicationConfig,
  inject,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter, withInMemoryScrolling } from '@angular/router';
import { routes } from './app.routes';
import { errorHandlerInterceptor } from './core/interceptors/error-handler.interceptor';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { ConfiguracionService } from './core/services/configuracion.service';
import { ClienteAuthService } from './core/services/cliente-auth.service';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideAppInitializer(() => inject(ConfiguracionService).inicializar()),
    provideAppInitializer(() => inject(ClienteAuthService).inicializar()),
    provideRouter(
      routes,
      withInMemoryScrolling({ scrollPositionRestoration: 'top', anchorScrolling: 'enabled' }),
    ),
    provideHttpClient(withInterceptors([authInterceptor, errorHandlerInterceptor])),
  ],
};