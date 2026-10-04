import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiResponse } from '../../core/models/api-response';
import { Paginated } from '../../core/models/paginated';
import {
  Devolucion,
  ETIQUETA_ESTADO_DEVOLUCION,
  TONO_ESTADO_DEVOLUCION,
  VentaDisponible,
} from '../../core/models/devolucion';
import { DevolucionesService } from '../../core/services/devoluciones.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface RegistrarEstado {
  abierto: boolean;
  guardando: boolean;
  error: string | null;
  busqueda: string;
  ventas: VentaDisponible[];
  cargandoVentas: boolean;
  ventaId: number | null;
  motivo: string;
  monto: number | null;
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
  ],
  selector: 'app-devoluciones',
  standalone: true,
  styleUrl: './devoluciones.scss',
  templateUrl: './devoluciones.html',
})
export class DevolucionesComponent implements OnInit {
  private readonly devolucionesService = inject(DevolucionesService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({ busqueda: '', estado: '' as string });

  readonly resultados = signal<Paginated<Devolucion> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly registrar = signal<RegistrarEstado>({
    abierto: false,
    guardando: false,
    error: null,
    busqueda: '',
    ventas: [],
    cargandoVentas: false,
    ventaId: null,
    motivo: '',
    monto: null,
  });

  readonly aprobarObjetivo = signal<Devolucion | null>(null);
  readonly aprobando = signal(false);

  readonly perPage = 15;

  ngOnInit(): void {
    this.cargar();
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', estado: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.devolucionesService
      .listar({
        busqueda: f.busqueda || undefined,
        estado: (f.estado as 'pendiente' | 'aprobada' | '') || undefined,
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
          this.toast.error(err.message ?? 'No se pudieron cargar las devoluciones.');
        },
      });
  }

  abrirRegistrar(): void {
    this.registrar.set({
      abierto: true,
      guardando: false,
      error: null,
      busqueda: '',
      ventas: [],
      cargandoVentas: false,
      ventaId: null,
      motivo: '',
      monto: null,
    });
    this.cargarVentas();
  }

  cerrarRegistrar(): void {
    if (this.registrar().guardando) {
      return;
    }
    this.registrar.set({ ...this.registrar(), abierto: false });
  }

  buscarVentas(): void {
    this.cargarVentas();
  }

  private cargarVentas(): void {
    const estado = this.registrar();
    this.registrar.set({ ...estado, cargandoVentas: true });

    this.devolucionesService.ventasDisponibles(estado.busqueda.trim() || undefined).subscribe({
      next: (res) => {
        const ventas = res.success && res.data?.data ? res.data.data : [];
        const sigueEnLista = ventas.some((v) => v.id === estado.ventaId);
        this.registrar.set({
          ...this.registrar(),
          ventas,
          cargandoVentas: false,
          ventaId: sigueEnLista ? estado.ventaId : null,
          monto: sigueEnLista ? estado.monto : null,
        });
      },
      error: (err) => {
        this.registrar.set({ ...this.registrar(), cargandoVentas: false, error: err.message ?? 'No se pudieron cargar las ventas.' });
      },
    });
  }

  onSeleccionarVenta(): void {
    const venta = this.registrar().ventas.find((v) => v.id === this.registrar().ventaId);
    const monto = venta ? parseFloat(String(venta.total)) || 0 : null;
    this.registrar.set({ ...this.registrar(), monto });
  }

  cambiarMonto(valor: string): void {
    this.registrar.set({
      ...this.registrar(),
      monto: valor === '' ? null : Number(valor),
    });
  }

  totalVentaDeVentaSeleccionada(): string {
    const venta = this.registrar().ventas.find((v) => v.id === this.registrar().ventaId);
    return venta?.total ?? '0';
  }

  registrarDevolucion(): void {
    const estado = this.registrar();

    if (!estado.ventaId) {
      this.registrar.set({ ...estado, error: 'Selecciona la venta a devolver.' });
      return;
    }
    if (!estado.motivo.trim()) {
      this.registrar.set({ ...estado, error: 'Indica el motivo de la devolución.' });
      return;
    }

    this.registrar.set({ ...estado, guardando: true, error: null });

    this.devolucionesService
      .crear({
        venta_id: estado.ventaId,
        motivo: estado.motivo.trim(),
        monto_reembolso: estado.monto ? Number(estado.monto) : null,
      })
      .subscribe({
        next: (res) => {
          this.registrar.set({ ...this.registrar(), guardando: false });
          if (res.success) {
            this.toast.success(res.message ?? 'Devolución registrada.');
            this.registrar.set({ ...this.registrar(), abierto: false });
            this.pagina.set(1);
            this.cargar();
          }
        },
        error: (err) => {
          this.registrar.set({ ...this.registrar(), guardando: false, error: err.message ?? 'No se pudo registrar la devolución.' });
        },
      });
  }

  solicitarAprobar(devolucion: Devolucion): void {
    if (devolucion.estado === 'pendiente') {
      this.aprobarObjetivo.set(devolucion);
    }
  }

  cancelarAprobar(): void {
    this.aprobarObjetivo.set(null);
  }

  confirmarAprobar(): void {
    const devolucion = this.aprobarObjetivo();
    if (!devolucion || this.aprobando()) {
      return;
    }

    this.aprobando.set(true);

    this.devolucionesService.aprobar(devolucion.id).subscribe({
      next: (res) => {
        this.aprobando.set(false);
        this.aprobarObjetivo.set(null);
        if (res.success) {
          this.toast.success(res.message ?? 'Devolución aprobada.');
          this.cargar();
        }
      },
      error: (err) => {
        this.aprobando.set(false);
        this.aprobarObjetivo.set(null);
        this.toast.error(err.message ?? 'No se pudo aprobar la devolución.');
      },
    });
  }

  esPendiente(devolucion: Devolucion): boolean {
    return devolucion.estado === 'pendiente';
  }

  etiquetaEstado(devolucion: Devolucion): string {
    return ETIQUETA_ESTADO_DEVOLUCION[devolucion.estado] ?? devolucion.estado;
  }

  tonoEstado(devolucion: Devolucion): string {
    return TONO_ESTADO_DEVOLUCION[devolucion.estado] ?? 'neutral';
  }

  nombreCliente(devolucion: Devolucion): string {
    return devolucion.pedido?.cliente?.nombre ?? '—';
  }

  numeroVenta(devolucion: Devolucion): string {
    return devolucion.venta?.numero_venta ?? '—';
  }

  totalVenta(devolucion: Devolucion): string | null {
    return devolucion.venta?.total ?? null;
  }

  numeroPedido(devolucion: Devolucion): string {
    return devolucion.pedido?.numero_pedido ?? '—';
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const fechaObj = new Date(fecha);
    if (Number.isNaN(fechaObj.getTime())) {
      return '—';
    }
    return fechaObj.toLocaleDateString('es-AR');
  }
}