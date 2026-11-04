import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import { ClienteAuthService } from '../../core/services/cliente-auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-mi-perfil',
  styleUrl: '../auth/auth-form.scss',
  templateUrl: './mi-perfil.html',
})
export class MiPerfilComponent implements OnInit {
  private readonly auth = inject(ClienteAuthService);

  readonly cliente = this.auth.cliente;

  nombre = '';
  telefono = '';
  direccion = '';
  ciudad = '';

  actual = '';
  nueva = '';
  confirmar = '';

  readonly guardando = signal(false);
  readonly guardandoPassword = signal(false);
  readonly okPerfil = signal<string | null>(null);
  readonly errorPerfil = signal<string | null>(null);
  readonly okPassword = signal<string | null>(null);
  readonly errorPassword = signal<string | null>(null);

  ngOnInit(): void {
    const c = this.cliente();
    if (c) {
      this.nombre = c.nombre;
      this.telefono = c.telefono ?? '';
      this.direccion = c.direccion ?? '';
      this.ciudad = c.ciudad ?? '';
    }
  }

  guardarPerfil(): void {
    if (!this.nombre.trim()) {
      this.errorPerfil.set('El nombre no puede quedar vacío.');
      return;
    }

    this.guardando.set(true);
    this.errorPerfil.set(null);
    this.okPerfil.set(null);

    this.auth
      .actualizarPerfil({
        nombre: this.nombre.trim(),
        telefono: this.telefono.trim() || null,
        direccion: this.direccion.trim() || null,
        ciudad: this.ciudad.trim() || null,
      })
      .subscribe({
        next: () => {
          this.guardando.set(false);
          this.okPerfil.set('Datos actualizados correctamente.');
        },
        error: (err: ApiError) => {
          this.guardando.set(false);
          this.errorPerfil.set(err.message ?? 'No pudimos guardar los cambios.');
        },
      });
  }

  cambiarPassword(): void {
    if (!this.actual) {
      this.errorPassword.set('Indicá tu contraseña actual.');
      return;
    }

    if (this.nueva.length < 8) {
      this.errorPassword.set('La nueva contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (this.nueva !== this.confirmar) {
      this.errorPassword.set('Las contraseñas no coinciden.');
      return;
    }

    this.guardandoPassword.set(true);
    this.errorPassword.set(null);
    this.okPassword.set(null);

    this.auth
      .cambiarPassword({
        actual: this.actual,
        password: this.nueva,
        password_confirmation: this.confirmar,
      })
      .subscribe({
        next: () => {
          this.guardandoPassword.set(false);
          this.okPassword.set('Contraseña actualizada correctamente.');
          this.actual = '';
          this.nueva = '';
          this.confirmar = '';
        },
        error: (err: ApiError) => {
          this.guardandoPassword.set(false);
          this.errorPassword.set(err.message ?? 'No pudimos cambiar la contraseña.');
        },
      });
  }
}
