import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-registrarme',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './registrarme.html',
})
export class RegistrarmeComponent {
  private readonly auth = inject(ClienteAuthService);
  private readonly router = inject(Router);

  nombre = '';
  email = '';
  telefono = '';
  password = '';
  passwordConfirmation = '';

  readonly enviando = signal(false);
  readonly error = signal<string | null>(null);

  enviar(): void {
    if (
      !this.nombre.trim() ||
      !this.email.trim() ||
      !this.telefono.trim() ||
      !this.password ||
      !this.passwordConfirmation
    ) {
      this.error.set('Completá todos los campos.');
      return;
    }

    if (this.password.length < 8) {
      this.error.set('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (this.password !== this.passwordConfirmation) {
      this.error.set('Las contraseñas no coinciden.');
      return;
    }

    this.enviando.set(true);
    this.error.set(null);

    this.auth
      .registro({
        nombre: this.nombre.trim(),
        email: this.email.trim(),
        telefono: this.telefono.trim(),
        password: this.password,
        password_confirmation: this.passwordConfirmation,
      })
      .subscribe({
        next: () => {
          this.enviando.set(false);
          this.router.navigateByUrl('/mi-perfil');
        },
        error: (err: ApiError) => {
          this.enviando.set(false);
          this.error.set(err.message ?? 'No pudimos crear tu cuenta.');
        },
      });
  }
}
