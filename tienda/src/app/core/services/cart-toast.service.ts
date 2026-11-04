import { Injectable, signal } from '@angular/core';

/**
 * Controla el aviso de "Agregado al carrito". Reutiliza una sola instancia:
 * cada llamada a mostrar() reinicia el temporizador de cierre automático.
 */
@Injectable({ providedIn: 'root' })
export class CartToastService {
  readonly visible = signal(false);

  private temporizador: ReturnType<typeof setTimeout> | null = null;

  mostrar(): void {
    this.visible.set(true);
    this.reiniciarTemporizador();
  }

  cerrar(): void {
    this.visible.set(false);
    this.limpiarTemporizador();
  }

  private reiniciarTemporizador(): void {
    this.limpiarTemporizador();
    this.temporizador = setTimeout(() => {
      this.visible.set(false);
      this.temporizador = null;
    }, 4000);
  }

  private limpiarTemporizador(): void {
    if (this.temporizador !== null) {
      clearTimeout(this.temporizador);
      this.temporizador = null;
    }
  }
}
