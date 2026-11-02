import { Component, signal } from '@angular/core';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../core/services/auth.service';
import { ConfiguracionService } from '../../core/services/configuracion.service';

@Component({
  imports: [FormsModule, CommonModule],
  selector: 'app-login',
  styleUrl: './login.scss',
  templateUrl: './login.html',
})
export class LoginComponent {
  email = signal('');
  password = signal('');
  errorMessage: string | null = null;
  loading = false;
  logoUrl = signal<string | null>(null);

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly config: ConfiguracionService,
  ) {
    this.cargarLogo();
  }

  private cargarLogo(): void {
    this.config.logoTienda().subscribe({
      next: (res) => {
        this.logoUrl.set(res.data?.logo_url ?? null);
      },
      error: () => {
        // Sin sesión pública: si falla, se queda sin logo y se muestra el texto fijo.
        this.logoUrl.set(null);
      },
    });
  }

  async onSubmit(): Promise<void> {
    this.errorMessage = null;
    this.loading = true;

    this.auth.login(this.email(), this.password()).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success && res.data) {
          this.auth.iniciarSesion(res.data);
          this.router.navigate(['/dashboard']);
        }
      },
      error: (err) => {
        this.loading = false;
        this.errorMessage = err.message ?? 'No se pudo iniciar sesión.';
      },
    });
  }
}