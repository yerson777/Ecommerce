import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { TokenService } from '../services/token.service';

export const authGuard: CanActivateFn = () => {
  const authService = inject(TokenService);
  const router = inject(Router);

  if (authService.getToken()) {
    return true;
  }

  return router.createUrlTree(['/login']);
};