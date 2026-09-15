import { Component, signal } from '@angular/core';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../core/services/auth.service';

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

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
  ) {}

  async onSubmit(): Promise<void> {
    this.errorMessage = null;
    this.loading = true;

    this.auth.login(this.email(), this.password()).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success && res.data) {
          this.auth.saveToken(res.data.token);
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