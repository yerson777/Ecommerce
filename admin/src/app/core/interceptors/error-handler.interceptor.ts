import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { EMPTY, catchError, throwError } from 'rxjs';
import { ApiError } from '../models/api-response';
import { AuthService } from '../services/auth.service';

const LOGIN_URL = '/v1/admin/auth/login';
const LOGOUT_URL = '/v1/admin/auth/logout';

/** Evita redirecciones múltiples cuando llegan varios 401 a la vez. */
let redirigiendo = false;

export const errorHandlerInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // Un 401 en el login son credenciales inválidas: debe mostrar su error.
      const esLogin = req.url.endsWith(LOGIN_URL);

      if (error.status === 401 && !esLogin) {
        const esLogout = req.url.endsWith(LOGOUT_URL);
        authService.limpiarSesionLocal();

        // El 401 del propio logout no debe redirigir ni mostrar otro error.
        if (!esLogout && !redirigiendo && !router.url.startsWith('/login')) {
          redirigiendo = true;
          void router.navigate(['/login']).finally(() => {
            redirigiendo = false;
          });
        }

        // Sesión inválida: se silencia para no mostrar banners/toasts sueltos.
        return EMPTY;
      }

      const apiError: ApiError = {
        success: false,
        message: 'Error de conexión con el servidor.',
        errors: null,
        status: error.status ?? undefined,
      };

      if (error.status === 0) {
        apiError.message = 'No se pudo conectar con el servidor. Verifica tu conexión.';
      } else if (error.error?.message) {
        apiError.message = error.error.message;
        apiError.errors = error.error.errors ?? null;
      } else if (error.status === 422) {
        apiError.message = 'Datos no válidos. Revisa el formulario.';
        apiError.errors = error.error?.errors ?? null;
      }

      return throwError(() => apiError);
    }),
  );
};
