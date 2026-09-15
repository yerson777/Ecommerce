import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { ApiError } from '../models/api-response';
import { TokenService } from '../services/token.service';

export const errorHandlerInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(TokenService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
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

      if (error.status === 401) {
        authService.clearToken();
      }

      return throwError(() => apiError);
    }),
  );
};