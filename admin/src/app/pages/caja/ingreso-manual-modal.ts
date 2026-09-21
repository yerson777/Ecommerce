import { Component, inject, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { IngresoManualPayload, Movimiento } from '../../core/models/caja';
import { CajaService } from '../../core/services/caja.service';
import { ToastService } from '../../core/services/toast.service';
import { ModalComponent } from '../../shared/components/modal/modal';

@Component({
  imports: [FormsModule, ModalComponent],
  selector: 'app-ingreso-manual-modal',
  standalone: true,
  styleUrl: './ingreso-manual-modal.scss',
  templateUrl: './ingreso-manual-modal.html',
})
export class IngresoManualModalComponent {
  readonly registrado = output<Movimiento>();
  readonly cerrado = output<void>();

  private readonly cajaService = inject(CajaService);
  private readonly toast = inject(ToastService);

  descripcion = '';
  monto: number | null = null;
  fecha = this.hoy();

  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  guardar(): void {
    if (!this.descripcion.trim()) {
      this.errorBanner.set('Indica una descripción del ingreso.');
      this.errores.set(null);
      return;
    }

    if (this.monto == null || this.monto <= 0) {
      this.errorBanner.set('Indica un monto mayor a cero.');
      this.errores.set(null);
      return;
    }

    if (!this.fecha) {
      this.errorBanner.set('Indica la fecha del ingreso.');
      this.errores.set(null);
      return;
    }

    const payload: IngresoManualPayload = {
      tipo: 'ingreso',
      monto: this.monto,
      descripcion: this.descripcion.trim(),
      fecha: this.fecha,
    };

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    this.cajaService.registrarIngreso(payload).subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success('Ingreso registrado en caja.');
          this.registrado.emit(res.data);
        }
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorBanner.set(err.message ?? 'No se pudo registrar el ingreso.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  private hoy(): string {
    const ahora = new Date();
    const offset = ahora.getTimezoneOffset();
    const local = new Date(ahora.getTime() - offset * 60 * 1000);
    return local.toISOString().slice(0, 10);
  }
}