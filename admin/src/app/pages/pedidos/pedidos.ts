import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { interval } from 'rxjs';
import { FormsModule } from '@angular/forms';
import { ETIQUETA_ESTADO_PEDIDO, TONO_ESTADO_PEDIDO, EstadoPedido, Pedido } from '../../core/models/pedido';
import { Paginated } from '../../core/models/paginated';
import { PedidosService } from '../../core/services/pedidos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface TransicionObjetivo {
  pedido: Pedido;
  estado: EstadoPedido;
}

function esEstadoTransicionable(estado: string): estado is EstadoPedido {
  return ['pendiente', 'confirmado', 'cancelado', 'completado'].includes(estado);
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
  selector: 'app-pedidos',
  standalone: true,
  styleUrl: './pedidos.scss',
  templateUrl: './pedidos.html',
})
export class PedidosComponent implements OnInit {
  private readonly pedidosService = inject(PedidosService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly filtros = signal({
    busqueda: '',
    estado: '' as '' | EstadoPedido,
    fecha_desde: '',
    fecha_hasta: '',
  });

  readonly resultados = signal<Paginated<Pedido> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly detalle = signal<Pedido | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);
  readonly transicion = signal<TransicionObjetivo | null>(null);
  readonly transicionando = signal(false);

  readonly perPage = 15;

  ngOnInit(): void {
    this.cargar();
    this.aplicarRefrescoAutomatico();
  }

  private aplicarRefrescoAutomatico(): void {
    interval(30000)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        if (!this.cargando()) {
          this.cargar();
        }
      });
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', estado: '', fecha_desde: '', fecha_hasta: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.pedidosService
      .listar({
        busqueda: f.busqueda || undefined,
        estado: f.estado || null,
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
          this.toast.error(err.message ?? 'No se pudieron cargar los pedidos.');
        },
      });
  }

  verDetalle(pedido: Pedido): void {
    this.detalleAbierto.set(true);
    this.detalleCargando.set(true);
    this.detalle.set(null);

    this.pedidosService.detalle(pedido.id).subscribe({
      next: (res) => {
        this.detalleCargando.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
        }
      },
      error: (err) => {
        this.detalleCargando.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar el detalle.');
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  pedirTransicion(estado: string): void {
    const detalle = this.detalle();
    if (!detalle || this.transicion()) {
      return;
    }
    this.transicion.set({ pedido: detalle, estado: estado as EstadoPedido });
  }

  textoTituloTransicion(): string {
    const transicion = this.transicion();
    if (!transicion) {
      return '';
    }
    if (transicion.estado === 'confirmado') {
      return 'Confirmar pedido';
    }
    if (transicion.estado === 'completado') {
      return 'Completar pedido';
    }
    return 'Cancelar pedido';
  }

  textoMensajeTransicion(): string {
    const transicion = this.transicion();
    if (!transicion) {
      return '';
    }
    const numero = transicion.pedido.numero_pedido;
    if (transicion.estado === 'confirmado') {
      return `Se confirmará el pedido ${numero}. Las prendas permanecen reservadas hasta completarlo.`;
    }
    if (transicion.estado === 'completado') {
      return `El pedido ${numero} pasará a Completado: se registrará la venta con los precios históricos y las prendas quedarán marcadas como vendidas. Esta acción no se puede deshacer.`;
    }
    return `Se cancelará el pedido ${numero} y las prendas volverán a estar disponibles. Esta acción no se puede deshacer.`;
  }

  etiquetaConfirmar(): string {
    return this.transicion()?.estado === 'cancelado' ? 'Cancelar pedido' : 'Confirmar';
  }

  confirmarTransicion(): void {
    const transicion = this.transicion();
    if (!transicion || this.transicionando()) {
      return;
    }

    this.transicionando.set(true);
    this.pedidosService.cambiarEstado(transicion.pedido.id, transicion.estado).subscribe({
      next: () => {
        this.transicionando.set(false);
        this.transicion.set(null);
        this.toast.success('Estado del pedido actualizado.');
        this.cargar();
        this.verDetalle(transicion.pedido);
      },
      error: (err) => {
        this.transicionando.set(false);
        this.toast.error(err.message ?? 'No se pudo actualizar el estado.');
      },
    });
  }

  cancelarTransicion(): void {
    this.transicion.set(null);
  }

  etiquetaEstado(estado: string): string {
    if (esEstadoTransicionable(estado)) {
      return ETIQUETA_ESTADO_PEDIDO[estado];
    }
    return estado;
  }

  tonoEstado(estado: string): string {
    if (esEstadoTransicionable(estado)) {
      return TONO_ESTADO_PEDIDO[estado];
    }
    return 'neutral';
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }
}