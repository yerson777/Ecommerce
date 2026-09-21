import { Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { CajaService } from '../../core/services/caja.service';
import { CategoriaGastoRef, Gasto, GastoPayload } from '../../core/models/caja';
import { MetodoPagoRef } from '../../core/models/pago';
import { ToastService } from '../../core/services/toast.service';
import { ModalComponent } from '../../shared/components/modal/modal';

@Component({
  imports: [FormsModule, ModalComponent],
  selector: 'app-registro-gasto-modal',
  standalone: true,
  styleUrl: './registro-gasto-modal.scss',
  templateUrl: './registro-gasto-modal.html',
})
export class RegistroGastoModalComponent {
  readonly categorias = input<CategoriaGastoRef[]>([]);
  readonly metodos = input<MetodoPagoRef[]>([]);

  readonly registrado = output<Gasto>();
  readonly cerrado = output<void>();

  private readonly cajaService = inject(CajaService);
  private readonly toast = inject(ToastService);

  concepto = '';
  monto: number | null = null;
  categoriaId: number | null = null;
  metodoId: number | null = null;
  fecha = this.hoy();
  observacion = '';

  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  guardar(): void {
    if (!this.concepto.trim()) {
      this.errorBanner.set('Indica el concepto del gasto.');
      this.errores.set(null);
      return;
    }

    if (this.monto == null || this.monto <= 0) {
      this.errorBanner.set('Indica un monto mayor a cero.');
      this.errores.set(null);
      return;
    }

    if (this.categoriaId == null) {
      this.errorBanner.set('Selecciona la categoría del gasto.');
      this.errores.set(null);
      return;
    }

    if (this.metodoId == null) {
      this.errorBanner.set('Selecciona el método de pago.');
      this.errores.set(null);
      return;
    }

    if (!this.fecha) {
      this.errorBanner.set('Indica la fecha del gasto.');
      this.errores.set(null);
      return;
    }

    const payload: GastoPayload = {
      concepto: this.concepto.trim(),
      monto: this.monto,
      categoria_gasto_id: this.categoriaId,
      metodo_pago_id: this.metodoId,
      fecha_gasto: this.fecha,
      observacion: this.observacion.trim() || null,
    };

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    this.cajaService.registrarGasto(payload).subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success('Gasto registrado.');
          this.registrado.emit(res.data);
        }
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorBanner.set(err.message ?? 'No se pudo registrar el gasto.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  private hoy(): string {
    const ahora = new Date();
    const offset = ahora.getTimezoneOffset();
    const local = new Date(ahora.getTime() - offset * 60 * 1000);
    return local.toISOString().slice(0, 10);
  }
}