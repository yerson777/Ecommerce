import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-restablecer',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './restablecer.html',
})
export class RestablecerComponent implements OnInit {
  private readonly auth = inject(ClienteAuthService);
  private readonly route = inject(ActivatedRoute);

  email = '';
  token = '';
  password = '';
  passwordConfirmation = '';

  readonly enviando = signal(false);
  readonly listo = signal(false);
  readonly error = signal<string | null>(null);

  ngOnInit(): void {
    const params = this.route.snapshot.queryParamMap;
    this.email = params.get('email') ?? '';
    this.token = params.get('token') ?? '';

    if (!this.email || !this.token) {
      this.error.set('El enlace no es válido o está incompleto.');
    }
  }

  enviar(): void {
    if (!this.email || !this.token) {
      this.error.set('El enlace no es válido o está incompleto.');
      return;
    }

    if (this.password.length < 8) {
      this.error.set('La nueva contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (this.password !== this.passwordConfirmation) {
      this.error.set('Las contraseñas no coinciden.');
      return;
    }

    this.enviando.set(true);
    this.error.set(null);

    this.auth
      .restablecer({
        email: this.email,
        token: this.token,
        password: this.password,
        password_confirmation: this.passwordConfirmation,
      })
      .subscribe({
        next: () => {
          this.enviando.set(false);
          this.listo.set(true);
        },
        error: (err: ApiError) => {
          this.enviando.set(false);
          this.error.set(err.message ?? 'No pudimos restablecer la contraseña.');
        },
      });
  }
}
