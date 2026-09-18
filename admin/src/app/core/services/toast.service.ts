import { Injectable, signal } from '@angular/core';

export type ToastType = 'success' | 'error' | 'info';

export interface Toast {
  id: number;
  tipo: ToastType;
  message: string;
}

@Injectable({ providedIn: 'root' })
export class ToastService {
  readonly toasts = signal<Toast[]>([]);

  private contador = 0;

  success(mensaje: string): void {
    this.mostrar('success', mensaje);
  }

  error(mensaje: string): void {
    this.mostrar('error', mensaje);
  }

  info(mensaje: string): void {
    this.mostrar('info', mensaje);
  }

  quitar(id: number): void {
    this.toasts.update((toasts) => toasts.filter((toast) => toast.id !== id));
  }

  private mostrar(tipo: ToastType, mensaje: string): void {
    const id = ++this.contador;

    this.toasts.update((toasts) => [...toasts, { id, tipo, message: mensaje }]);

    setTimeout(() => this.quitar(id), 4200);
  }
}