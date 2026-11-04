import { HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { leerTokenCliente } from '../auth/token-storage';

/**
 * Adjunta el token de la cuenta de cliente a las llamadas a la API.
 * Las rutas públicas siguen funcionando aunque no haya token.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = leerTokenCliente();

  if (!token || !req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  return next(
    req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    }),
  );
};
