import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ClienteAuthService } from '../services/cliente-auth.service';

export const clienteAuthGuard: CanActivateFn = () => {
  const auth = inject(ClienteAuthService);

  if (auth.logueado()) {
    return true;
  }

  return inject(Router).createUrlTree(['/ingresar']);
};
