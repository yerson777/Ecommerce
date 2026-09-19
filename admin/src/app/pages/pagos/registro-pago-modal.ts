import { Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { MetodoPagoRef, Pago, PagoPayload } from '../../core/models/pago';
import { Pedido } from '../../core/models/pedido';
import { PagosService } from '../../core/services/pagos.service';
import { PedidosService } from '../../core/services/pedidos.service';
import { ToastService } from '../../core/services/toast.service';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';

@Component({
  imports: [FormsModule, ModalComponent, EmptyStateComponent],
  selector: 'app-registro-pago-modal',
  standalone: true,
  styleUrl: './registro-pago-modal.scss',
  templateUrl: './registro-pago-modal.html',
})
export class RegistroPagoModalComponent {
  readonly metodos = input<MetodoPagoRef[]>([]);

  readonly registrado = output<Pago>();
  readonly cerrado = output<void>();

  private readonly pagosService = inject(PagosService);
  private readonly pedidosService = inject(PedidosService);
  private readonly toast = inject(ToastService);

  busqueda = '';
  readonly pedidosEncontrados = signal<Pedido[]>([]);
  readonly buscando = signal(false);
  readonly busquedaHecha = signal(false);
  seleccionado: Pedido | null = null;

  monto: number | null = null;
  metodoId: number | null = null;
  fecha = '';
  estado: 'pendiente' | 'completado' = 'pendiente';
  referencia = '';
  nota = '';
  comprobante: File | null = null;
  comprobanteNombre = '';
  permitirExcedente = false;

  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  buscarPedidos(): void {
    this.buscando.set(true);
    this.busquedaHecha.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    this.pedidosService
      .listar({ busqueda: this.busqueda.trim() || undefined, per_page: 100 })
      .subscribe({
        next: (res) => {
          this.buscando.set(false);
          if (res.success && res.data) {
            this.pedidosEncontrados.set(res.data.data);
          }
        },
        error: (err: ApiError) => {
          this.buscando.set(false);
          this.errorBanner.set(err.message ?? 'No se pudieron buscar pedidos.');
        },
      });
  }

  seleccionar(pedido: Pedido): void {
    this.seleccionado = pedido;
    this.pedidosEncontrados.set([]);
    this.busqueda = pedido.numero_pedido;
    this.monto = this.saldoDe(pedido);

    if (this.metodoId == null) {
      const activos = this.metodos().filter((m) => m.activo);
      this.metodoId = activos[0]?.id ?? null;
    }

    this.estado = 'pendiente';
    this.referencia = '';
    this.nota = '';
    this.comprobante = null;
    this.comprobanteNombre = '';
    this.permitirExcedente = false;
    this.errorBanner.set(null);
    this.errores.set(null);
  }

  saldoPendiente(): number {
    const pedido = this.seleccionado;
    if (!pedido) {
      return 0;
    }
    const total = parseFloat(pedido.total);
    const pagado = parseFloat(pedido.total_pagado ?? '0');
    const saldo = pedido.saldo_pendiente != null ? parseFloat(pedido.saldo_pendiente) : total - pagado;
    return Number.isNaN(saldo) ? 0 : Math.max(0, saldo);
  }

  montoSuperaSaldo(): boolean {
    const monto = this.monto;
    return monto != null && monto > 0 && this.saldoPendiente() >= 0 && monto > this.saldoPendiente();
  }

  onComprobante(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    input.value = '';

    if (!archivo) {
      return;
    }

    if (!this.comprobanteValido(archivo)) {
      this.toast.error('El comprobante debe ser una imagen (JPG, PNG, WEBP, GIF) o PDF de hasta 5 MB.');
      return;
    }

    this.comprobante = archivo;
    this.comprobanteNombre = archivo.name;
  }

  quitarComprobante(): void {
    this.comprobante = null;
    this.comprobanteNombre = '';
  }

  guardar(): void {
    const pedido = this.seleccionado;

    if (!pedido) {
      this.errorBanner.set('Busca y selecciona el pedido a cobrar.');
      this.errores.set(null);
      return;
    }

    const monto = this.monto;

    if (monto == null || monto <= 0) {
      this.errorBanner.set('Indica un monto mayor a cero.');
      this.errores.set(null);
      return;
    }

    if (this.metodoId == null) {
      this.errorBanner.set('Selecciona el método de pago.');
      this.errores.set(null);
      return;
    }

    if (monto > this.saldoPendiente() && !this.permitirExcedente) {
      this.errorBanner.set(`El monto supera el saldo pendiente (Bs ${this.saldoPendiente().toFixed(2)}). Marca la opción de excedente para confirmar.`);
      this.errores.set(null);
      return;
    }

    if (this.estado === 'completado' && !this.fecha) {
      this.fecha = this.fechaLocal();
    }

    const payload: PagoPayload = {
      pedido_id: pedido.id,
      monto,
      metodo_pago_id: this.metodoId,
      fecha: this.estado === 'completado' ? this.fecha || null : null,
      estado: this.estado,
      referencia: this.referencia.trim() || null,
      nota: this.nota.trim() || null,
      permitir_excedente: this.permitirExcedente,
      comprobante: this.comprobante,
    };

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    this.pagosService.registrar(payload).subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success('Pago registrado.');
          this.registrado.emit(res.data);
        }
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorBanner.set(err.message ?? 'No se pudo registrar el pago.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  nombrePedido(pedido: Pedido): string {
    const total = this.moneda(pedido.total);
    const pagado = this.moneda(pedido.total_pagado);
    return `${pedido.numero_pedido} · ${pedido.cliente?.nombre ?? 'Sin cliente'} · Total ${total}`;
  }

  saldoDe(pedido: Pedido): number {
    const total = parseFloat(pedido.total);
    const pagado = parseFloat(pedido.total_pagado ?? '0');
    const saldo = pedido.saldo_pendiente != null ? parseFloat(pedido.saldo_pendiente) : total - pagado;
    return Math.max(0, Number.isNaN(saldo) ? 0 : saldo);
  }

  private fechaLocal(): string {
    const ahora = new Date();
    const offset = ahora.getTimezoneOffset();
    const local = new Date(ahora.getTime() - offset * 60 * 1000);
    return local.toISOString().slice(0, 16);
  }

  private comprobanteValido(archivo: File): boolean {
    const permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    return permitidos.includes(archivo.type) && archivo.size <= 5 * 1024 * 1024;
  }
}