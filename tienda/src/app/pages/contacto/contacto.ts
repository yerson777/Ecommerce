import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-contacto',
  styleUrl: './contacto.scss',
  templateUrl: './contacto.html',
})
export class ContactoComponent {
  readonly enviado = signal(false);

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
    this.limpiar();

    window.open(`https://wa.me/59162640247?text=${encodeURIComponent(texto)}`, '_blank', 'noopener');
  }

  enviarPorCorreo(): void {
    if (!this.formularioValido()) {
      return;
    }

    const asunto = this.asuntoFinal;
    const cuerpo = this.cuerpoFinal;
    this.enviado.set(true);
    this.limpiar();

    window.location.href =
      `mailto:hola@everlyboutique.com?subject=${encodeURIComponent(asunto)}` +
      `&body=${encodeURIComponent(cuerpo)}`;
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