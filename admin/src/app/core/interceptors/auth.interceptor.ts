import { HttpRequest, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { environment } from '../../../environments/environment';
import { TokenService } from '../services/token.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(TokenService);
  const token = authService.getToken();

  if (!token || !req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  const cloned: HttpRequest<unknown> = req.clone({
    setHeaders: { Authorization: `Bearer ${token}` },
  });

  return next(cloned);
};