import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-recuperar',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './recuperar.html',
})
export class RecuperarComponent {
  private readonly auth = inject(ClienteAuthService);

  email = '';
  readonly enviando = signal(false);
  readonly enviado = signal(false);
  readonly error = signal<string | null>(null);

  enviar(): void {
    if (!this.email.trim()) {
      this.error.set('Indicá tu correo electrónico.');
      return;
    }

    this.enviando.set(true);
    this.error.set(null);

    this.auth.recuperar(this.email.trim()).subscribe({
      next: () => {
        this.enviando.set(false);
        this.enviado.set(true);
      },
      error: (err: ApiError) => {
        this.enviando.set(false);
        this.error.set(err.message ?? 'No pudimos procesar la solicitud.');
      },
    });
  }
}
