import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MetodoEntregaConfig, MetodoPagoConfig } from '../../core/models/configuracion';
import { ConfiguracionService } from '../../core/services/configuracion.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [FormsModule, BadgeComponent, NotificationCenterComponent, SpinnerComponent],
  selector: 'app-configuracion',
  standalone: true,
  styleUrl: './configuracion.scss',
  templateUrl: './configuracion.html',
})
export class ConfiguracionComponent {
  private readonly service = inject(ConfiguracionService);
  private readonly toast = inject(ToastService);

  readonly metodosPago = signal<MetodoPagoConfig[]>([]);
  readonly metodosEntrega = signal<MetodoEntregaConfig[]>([]);
  readonly cargando = signal(true);
  readonly accionandoId = signal<number | null>(null);
  readonly guardandoCosto = signal<number | null>(null);

  constructor() {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);

    this.service.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosPago.set(res.data);
        }
        this.cargando.set(false);
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar los métodos de pago.');
      },
    });

    this.service.metodosEntrega().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosEntrega.set(res.data);
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron cargar los métodos de entrega.'),
    });
  }

  alternarPago(metodo: MetodoPagoConfig): void {
    if (this.accionandoId() !== null) {
      return;
    }

    const anterior = metodo.activo;
    metodo.activo = !anterior;
    this.accionandoId.set(metodo.id);

    this.service.actualizarMetodoPago(metodo.id, { activo: metodo.activo }).subscribe({
      next: (res) => {
        this.accionandoId.set(null);
        if (res.success && res.data) {
          metodo.activo = res.data.activo;
          this.toast.success(`Método de pago ${metodo.activo ? 'activado' : 'desactivado'}.`);
        }
      },
      error: (err) => {
        this.accionandoId.set(null);
        metodo.activo = anterior;
        this.toast.error(err.message ?? 'No se pudo actualizar el método de pago.');
      },
    });
  }

  alternarEntrega(metodo: MetodoEntregaConfig): void {
    if (this.accionandoId() !== null) {
      return;
    }

    const anterior = metodo.activo;
    metodo.activo = !anterior;
    this.accionandoId.set(metodo.id);

    this.service.actualizarMetodoEntrega(metodo.id, { activo: metodo.activo }).subscribe({
      next: (res) => {
        this.accionandoId.set(null);
        if (res.success && res.data) {
          metodo.activo = res.data.activo;
          this.toast.success(`Método de entrega ${metodo.activo ? 'activado' : 'desactivado'}.`);
        }
      },
      error: (err) => {
        this.accionandoId.set(null);
        metodo.activo = anterior;
        this.toast.error(err.message ?? 'No se pudo actualizar el método de entrega.');
      },
    });
  }

  guardarCosto(metodo: MetodoEntregaConfig): void {
    const costo = parseFloat(metodo.costo);
    if (Number.isNaN(costo) || costo < 0) {
      this.toast.error('Ingresá un costo válido.');
      return;
    }

    this.guardandoCosto.set(metodo.id);

    this.service.actualizarMetodoEntrega(metodo.id, { costo }).subscribe({
      next: (res) => {
        this.guardandoCosto.set(null);
        if (res.success && res.data) {
          metodo.costo = res.data.costo;
          this.toast.success('Costo de envío actualizado.');
        }
      },
      error: (err) => {
        this.guardandoCosto.set(null);
        this.toast.error(err.message ?? 'No se pudo actualizar el costo.');
      },
    });
  }

  moneda(valor: string | null | undefined): string {
    const numero = parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}