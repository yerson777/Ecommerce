import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ConfiguracionService } from '../../core/services/configuracion.service';
import { environment } from '../../../environments/environment';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-contacto',
  styleUrl: './contacto.scss',
  templateUrl: './contacto.html',
})
export class ContactoComponent {
  private readonly http = inject(HttpClient);
  readonly config = inject(ConfiguracionService);

  readonly enviado = signal(false);
  readonly enviando = signal(false);
  readonly error = signal<string | null>(null);

  readonly correo = computed(
    () => this.config.datos().correo?.trim() || 'contacto@everlyboutique.yervixsoft.com',
  );
  readonly telefono = computed(() => '+59178849663');
  readonly direccion = computed(
    () =>
      this.config.datos().direccion?.trim() ||
      'Calle Panamá 158 entre Argentina',
  );
  readonly horario = computed(
    () =>
      this.config.datos().horario?.trim() ||
      'Lunes a viernes 9:00 – 19:30 · Sábados 9:30 – 14:00',
  );
  readonly tiktok = computed(
    () => this.config.datos().tiktok?.trim() || 'https://www.tiktok.com/@everlyfashion',
  );
  readonly tiktokVisible = computed(() => {
    const url = this.tiktok();
    const usuario = url.split('/').filter(Boolean).pop() ?? '';
    return usuario.startsWith('@') ? usuario : `@${usuario}`;
  });

  nombre = '';
  email = '';
  asunto = '';
  mensaje = '';

  private get asuntoFinal(): string {
    return this.asunto.trim() || 'Consulta desde la tienda online';
  }

  private get cuerpoFinal(): string {
    return `Nombre: ${this.nombre}\nCorreo: ${this.email}\n\n${this.mensaje}`;
  }

  enviarPorWhatsApp(): void {
    if (!this.formularioValido()) {
      return;
    }

    const texto = `*${this.asuntoFinal}*\n${this.cuerpoFinal}`;
    this.enviado.set(true);
    this.error.set(null);
    this.limpiar();

    window.open(
      `${this.config.whatsappUrl()}?text=${encodeURIComponent(texto)}`,
      '_blank',
      'noopener',
    );
  }

  enviarPorCorreo(): void {
    if (!this.formularioValido()) {
      return;
    }

    this.error.set(null);
    this.enviando.set(true);

    const payload = {
      nombre: this.nombre.trim(),
      correo: this.email.trim(),
      asunto: this.asuntoFinal,
      mensaje: this.mensaje.trim(),
      // Honeypot: campo oculto, debe ir vacío.
      sitio_web: '',
    };

    this.http
      .post<{ success: boolean; message: string; errors?: unknown }>(
        `${environment.apiUrl}/store/contacto`,
        payload,
      )
      .subscribe({
        next: (res) => {
          this.enviado.set(res.success);
          this.error.set(null);
          this.enviando.set(false);
          this.limpiar();
        },
        error: (err) => {
          this.enviando.set(false);
          this.enviado.set(false);

          const backendMsg =
            err?.error?.errors ||
            err?.error?.message ||
            'No se pudo enviar el mensaje. Intentalo de nuevo mas tarde.';

          this.error.set(
            typeof backendMsg === 'string' ? backendMsg : 'No se pudo enviar el mensaje. Intentalo de nuevo mas tarde.',
          );
        },
      });
  }

  private formularioValido(): boolean {
    return (
      this.nombre.trim().length > 0 &&
      this.email.trim().length > 0 &&
      this.mensaje.trim().length > 0
    );
  }

  private limpiar(): void {
    this.nombre = '';
    this.email = '';
    this.asunto = '';
    this.mensaje = '';
  }
}
