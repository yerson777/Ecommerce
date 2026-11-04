import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-ingresar',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './ingresar.html',
})
export class IngresarComponent {
  private readonly auth = inject(ClienteAuthService);
  private readonly router = inject(Router);

  email = '';
  password = '';

  readonly enviando = signal(false);
  readonly error = signal<string | null>(null);

  enviar(): void {
    if (!this.email.trim() || !this.password) {
      this.error.set('Completá tu correo y contraseña.');
      return;
    }

    this.enviando.set(true);
    this.error.set(null);

    this.auth
      .login({ email: this.email.trim(), password: this.password })
      .subscribe({
        next: () => {
          this.enviando.set(false);
          this.router.navigateByUrl('/mi-perfil');
        },
        error: (err: ApiError) => {
          this.enviando.set(false);
          this.error.set(err.message ?? 'No pudimos iniciar sesión.');
        },
      });
  }
}
