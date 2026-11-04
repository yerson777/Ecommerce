import { Component, inject, OnInit, signal } from '@angular/core';
import { formatearFecha as formatearFechaCompartida, formatearMoneda } from '../../core/utils/formato';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Paginated } from '../../core/models/paginated';
import {
  ComprobanteInfo,
  EstadoPago,
  ETIQUETA_ESTADO_PAGO,
  MetodoPagoRef,
  Pago,
  PagoHistorial,
  PagoPorCobrar,
  TONO_ESTADO_PAGO,
} from '../../core/models/pago';
import {
  ETIQUETA_ESTADO_PAGO_PEDIDO,
  TONO_ESTADO_PAGO_PEDIDO,
} from '../../core/models/pedido';
import { PagosService } from '../../core/services/pagos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { RegistroPagoModalComponent } from './registro-pago-modal';

function esEstadoPago(estado: string): estado is EstadoPago {
  return ['pendiente', 'completado', 'anulado'].includes(estado);
}

interface PagoObjetivo {
  id: number;
  numero_pago: string;
  monto: string;
}

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    ConfirmDialogComponent,
    EmptyStateComponent,
    ModalComponent,
    NotificationCenterComponent,
    PaginatorComponent,
    SpinnerComponent,
    RegistroPagoModalComponent,
  ],
  selector: 'app-pagos',
  standalone: true,
  styleUrl: './pagos.scss',
  templateUrl: './pagos.html',
})
export class PagosComponent implements OnInit {
  private readonly pagosService = inject(PagosService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({
    busqueda: '',
    estado: '' as '' | EstadoPago,
    metodo_pago_id: null as number | null,
    fecha_desde: '',
    fecha_hasta: '',
  });

  readonly metodos = signal<MetodoPagoRef[]>([]);
  readonly resultados = signal<Paginated<Pago> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly perPage = 10;

  registrando = false;
  readonly detalle = signal<Pago | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);
  adjuntandoId: number | null = null;
  objetivoConfirmar: PagoObjetivo | null = null;
  objetivoAnular: PagoObjetivo | null = null;
  motivoAnular = '';
  transicionando = false;

  readonly porCobrar = signal<PagoPorCobrar[]>([]);
  readonly cargandoPorCobrar = signal(false);
  readonly mostrarPorCobrar = signal(false);

  ngOnInit(): void {
    this.cargarMetodos();
    this.cargar();
    this.cargarPorCobrar();
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({
      busqueda: '',
      estado: '',
      metodo_pago_id: null,
      fecha_desde: '',
      fecha_hasta: '',
    });
    this.onBuscar();
  }

  togglePorCobrar(): void {
    this.mostrarPorCobrar.set(!this.mostrarPorCobrar());
    if (this.mostrarPorCobrar()) {
      this.cargarPorCobrar();
    }
  }

  cargarPorCobrar(): void {
    this.cargandoPorCobrar.set(true);
    this.pagosService.porCobrar().subscribe({
      next: (res) => {
        this.cargandoPorCobrar.set(false);
        if (res.success && res.data) {
          this.porCobrar.set(res.data);
        }
      },
      error: () => {
        this.cargandoPorCobrar.set(false);
      },
    });
  }

  exportarExcel(): void {
    const f = this.filtros();
    this.pagosService
      .exportarExcel({
        busqueda: f.busqueda || undefined,
        estado: f.estado || null,
        metodo_pago_id: f.metodo_pago_id || null,
        fecha_desde: f.fecha_desde || null,
        fecha_hasta: f.fecha_hasta || null,
      })
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `pagos_${new Date().toISOString().slice(0, 10)}.xlsx`;
          a.click();
          URL.revokeObjectURL(url);
        },
        error: () => this.toast.error('No se pudo exportar a Excel.'),
      });
  }

  abrirWhatsApp(telefono: string | null): void {
    if (!telefono) {
      this.toast.error('El cliente no tiene teléfono registrado.');
      return;
    }
    const limpio = telefono.replace(/[^0-9]/g, '');
    window.open(`https://wa.me/${limpio}`, '_blank', 'noopener');
  }

  private cargarMetodos(): void {
    this.pagosService.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodos.set(res.data);
        }
      },
      error: () => undefined,
    });
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.pagosService
      .listar({
        busqueda: f.busqueda || undefined,
        estado: f.estado || null,
        metodo_pago_id: f.metodo_pago_id || null,
        fecha_desde: f.fecha_desde || null,
        fecha_hasta: f.fecha_hasta || null,
        page: this.pagina(),
        per_page: this.perPage,
      })
      .subscribe({
        next: (res) => {
          this.cargando.set(false);
          if (res.success && res.data) {
            this.resultados.set(res.data);
          }
        },
        error: (err) => {
          this.cargando.set(false);
          this.toast.error(err.message ?? 'No se pudieron cargar los pagos.');
        },
      });
  }

  abrirRegistro(): void {
    this.registrando = true;
  }

  cerrarRegistro(): void {
    this.registrando = false;
  }

  onRegistrado(pago: Pago): void {
    this.registrando = false;
    this.toast.success(`Pago ${pago.numero_pago} registrado.`);
    this.cargar();
  }

  verDetalle(pago: { id: number }): void {
    this.detalleAbierto.set(true);
    this.detalleCargando.set(true);
    this.detalle.set(null);

    this.pagosService.detalle(pago.id).subscribe({
      next: (res) => {
        this.detalleCargando.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
        }
      },
      error: (err) => {
        this.detalleCargando.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar el detalle del pago.');
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  pedirConfirmar(pago: PagoObjetivo): void {
    this.objetivoConfirmar = pago;
  }

  pedirAnular(pago: PagoObjetivo): void {
    this.objetivoAnular = pago;
    this.motivoAnular = '';
  }

  cancelarTransicion(): void {
    this.objetivoConfirmar = null;
    this.objetivoAnular = null;
    this.motivoAnular = '';
  }

  confirmarPago(): void {
    const objetivo = this.objetivoConfirmar;
    if (!objetivo || this.transicionando) {
      return;
    }

    this.transicionando = true;
    this.pagosService.confirmar(objetivo.id).subscribe({
      next: () => {
        this.transicionando = false;
        this.objetivoConfirmar = null;
        this.toast.success('Pago confirmado: cuenta para el saldo y la caja.');
        this.refrescar(objetivo);
      },
      error: (err: ApiError) => {
        this.transicionando = false;
        this.toast.error(err.message ?? 'No se pudo confirmar el pago.');
      },
    });
  }

  anularPago(): void {
    const objetivo = this.objetivoAnular;
    if (!objetivo || this.transicionando) {
      return;
    }

    this.transicionando = true;
    this.pagosService.anular(objetivo.id, this.motivoAnular).subscribe({
      next: () => {
        this.transicionando = false;
        this.objetivoAnular = null;
        this.motivoAnular = '';
        this.toast.success('Pago anulado: no cuenta para el saldo ni la caja.');
        this.refrescar(objetivo);
      },
      error: (err: ApiError) => {
        this.transicionando = false;
        this.toast.error(err.message ?? 'No se pudo anular el pago.');
      },
    });
  }

  verComprobante(comprobante: ComprobanteInfo, pagoId: number): void {
    this.pagosService.descargarComprobante(pagoId).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60_000);
      },
      error: () => this.toast.error('No se pudo descargar el comprobante.'),
    });
  }

  onAdjuntarComprobante(historial: PagoHistorial, event: Event): void {
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

    this.adjuntandoId = historial.id;

    this.pagosService.adjuntarComprobante(historial.id, archivo).subscribe({
      next: () => {
        this.adjuntandoId = null;
        this.toast.success('Comprobante adjuntado.');
        this.recargarDetalle();
      },
      error: (err: ApiError) => {
        this.adjuntandoId = null;
        this.toast.error(err.message ?? 'No se pudo adjuntar el comprobante.');
      },
    });
  }

  private comprobanteValido(archivo: File): boolean {
    const permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    return permitidos.includes(archivo.type) && archivo.size <= 5 * 1024 * 1024;
  }

  private recargarDetalle(): void {
    const detalle = this.detalle();
    if (detalle) {
      this.verDetalle(detalle);
    }
  }

  private refrescar(objetivo: PagoObjetivo): void {
    this.cargar();
    if (this.detalle()?.id === objetivo.id) {
      this.verDetalle(objetivo);
    }
  }

  etiquetaEstado(estado: string): string {
    return esEstadoPago(estado) ? ETIQUETA_ESTADO_PAGO[estado] : estado;
  }

  tonoEstado(estado: string): string {
    return esEstadoPago(estado) ? TONO_ESTADO_PAGO[estado] : 'neutral';
  }

  etiquetaEstadoPedido(estado: string): string {
    if (esEstadoPagoPedido(estado)) {
      return ETIQUETA_ESTADO_PAGO_PEDIDO[estado];
    }
    return estado;
  }

  tonoEstadoPedido(estado: string): string {
    if (esEstadoPagoPedido(estado)) {
      return TONO_ESTADO_PAGO_PEDIDO[estado];
    }
    return 'neutral';
  }

  readonly moneda = formatearMoneda;

  readonly formatearFecha = formatearFechaCompartida;
}

function esEstadoPagoPedido(estado: string): estado is keyof typeof ETIQUETA_ESTADO_PAGO_PEDIDO {
  return ['pendiente', 'parcial', 'pagado', 'cancelado'].includes(estado);
}