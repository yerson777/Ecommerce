import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  Cliente,
  ClienteDuplicadoRef,
  ClienteFiltro,
  ETIQUETA_FILTRO_CLIENTE,
} from '../../core/models/cliente';
import {
  ETIQUETA_ESTADO_DEVOLUCION,
  TONO_ESTADO_DEVOLUCION,
} from '../../core/models/devolucion';
import { Paginated } from '../../core/models/paginated';
import { ETIQUETA_ESTADO_PEDIDO, TONO_ESTADO_PEDIDO, EstadoPedido } from '../../core/models/pedido';
import { ETIQUETA_ESTADO_RESERVA, TONO_ESTADO_RESERVA } from '../../core/models/reserva';
import { ETIQUETA_ESTADO_VENTA, TONO_ESTADO_VENTA } from '../../core/models/venta';
import { ClientesService } from '../../core/services/clientes.service';
import { ToastService } from '../../core/services/toast.service';
import { enlaceWhatsApp } from '../../core/utils/whatsapp';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { ClienteFormModalComponent } from './cliente-form-modal';
import { ClientesDuplicadosModalComponent } from './clientes-duplicados-modal';

interface OpcionFiltro {
  valor: ClienteFiltro;
  etiqueta: string;
}

function esEstadoPedido(estado: string): estado is EstadoPedido {
  return estado in ETIQUETA_ESTADO_PEDIDO;
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
    ClienteFormModalComponent,
    ClientesDuplicadosModalComponent,
  ],
  selector: 'app-clientes',
  standalone: true,
  styleUrl: './clientes.scss',
  templateUrl: './clientes.html',
})
export class ClientesComponent implements OnInit {
  private readonly clientesService = inject(ClientesService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({ busqueda: '', filtro: '' as ClienteFiltro });

  readonly opcionesFiltro: OpcionFiltro[] = [
    { valor: '', etiqueta: 'Todos' },
    ...(Object.keys(ETIQUETA_FILTRO_CLIENTE) as Exclude<ClienteFiltro, ''>[]).map((valor) => ({
      valor,
      etiqueta: ETIQUETA_FILTRO_CLIENTE[valor],
    })),
  ];

  readonly resultados = signal<Paginated<Cliente> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly detalle = signal<Cliente | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);

  readonly modalFormulario = signal<{ abierto: boolean; cliente: Cliente | null }>({
    abierto: false,
    cliente: null,
  });

  readonly modalDuplicados = signal(false);
  readonly duplicados = signal<ClienteDuplicadoRef[][]>([]);
  readonly duplicadosCargando = signal(false);

  eliminarObjetivo: Cliente | null = null;
  bloqueoObjetivo: Cliente | null = null;

  readonly perPage = 15;

  ngOnInit(): void {
    this.cargar();
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  setFiltro(valor: ClienteFiltro): void {
    this.filtros.update((f) => ({ ...f, filtro: valor }));
    this.onBuscar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', filtro: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.clientesService
      .listar({
        busqueda: f.busqueda || undefined,
        filtro: f.filtro || undefined,
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
          this.toast.error(err.message ?? 'No se pudieron cargar los clientes.');
        },
      });
  }

  verDetalle(cliente: Cliente): void {
    this.detalleAbierto.set(true);
    this.detalleCargando.set(true);
    this.detalle.set(null);

    this.clientesService.detalle(cliente.id).subscribe({
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

  nuevoCliente(): void {
    this.modalFormulario.set({ abierto: true, cliente: null });
  }

  editarCliente(cliente: Cliente): void {
    this.detalleAbierto.set(false);
    this.modalFormulario.set({ abierto: true, cliente });
  }

  cerrarFormulario(): void {
    this.modalFormulario.set({ abierto: false, cliente: null });
  }

  onGuardado(): void {
    this.cerrarFormulario();
    this.cargar();
  }

  solicitarEliminar(cliente: Cliente): void {
    this.cerrarFormulario();
    this.detalleAbierto.set(false);
    this.eliminarObjetivo = cliente;
  }

  confirmarEliminar(): void {
    const cliente = this.eliminarObjetivo;
    if (!cliente) {
      return;
    }

    this.clientesService.eliminar(cliente.id).subscribe({
      next: (res) => {
        this.eliminarObjetivo = null;
        if (res.success) {
          this.toast.success('Cliente eliminado.');
          this.cargar();
        }
      },
      error: (err) => {
        this.eliminarObjetivo = null;
        this.toast.error(err.message ?? 'No se pudo eliminar el cliente.');
      },
    });
  }

  solicitarBloqueo(cliente: Cliente): void {
    this.bloqueoObjetivo = cliente;
  }

  confirmarBloqueo(): void {
    const cliente = this.bloqueoObjetivo;
    if (!cliente) {
      return;
    }

    this.bloqueoObjetivo = null;
    this.cambiarBloqueo(cliente, true);
  }

  desbloquear(cliente: Cliente): void {
    this.cambiarBloqueo(cliente, false);
  }

  private cambiarBloqueo(cliente: Cliente, bloqueado: boolean): void {
    this.clientesService.actualizar(cliente.id, { bloqueado }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(bloqueado ? 'Cliente bloqueado.' : 'Cliente desbloqueado.');
          this.cargar();
          if (this.detalleAbierto() && this.detalle()?.id === cliente.id) {
            this.verDetalle(cliente);
          }
        }
      },
      error: (err) => {
        this.toast.error(err.message ?? 'No se pudo actualizar el estado del cliente.');
      },
    });
  }

  abrirDuplicados(): void {
    this.modalDuplicados.set(true);
    this.cargarDuplicados();
  }

  cerrarDuplicados(): void {
    this.modalDuplicados.set(false);
  }

  private cargarDuplicados(): void {
    this.duplicadosCargando.set(true);

    this.clientesService.duplicados().subscribe({
      next: (res) => {
        this.duplicadosCargando.set(false);
        if (res.success && res.data) {
          this.duplicados.set(res.data);
        }
      },
      error: (err) => {
        this.duplicadosCargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar los duplicados.');
      },
    });
  }

  onUnido(): void {
    this.cargarDuplicados();
    this.cargar();
  }

  exportar(): void {
    const f = this.filtros();

    this.clientesService
      .exportar({ busqueda: f.busqueda || undefined, filtro: f.filtro || undefined })
      .subscribe({
        next: (blob) => this.descargarBlob(blob, 'everly_clientes.csv'),
        error: (err) => this.toast.error(err.message ?? 'No se pudo exportar el listado.'),
      });
  }

  private descargarBlob(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  etiquetaEstado(estado: string): string {
    return esEstadoPedido(estado) ? ETIQUETA_ESTADO_PEDIDO[estado] : estado;
  }

  tonoEstado(estado: string): string {
    return esEstadoPedido(estado) ? TONO_ESTADO_PEDIDO[estado] : 'neutral';
  }

  etiquetaVenta(estado: string): string {
    return ETIQUETA_ESTADO_VENTA[estado as keyof typeof ETIQUETA_ESTADO_VENTA] ?? estado;
  }

  tonoVenta(estado: string): string {
    return TONO_ESTADO_VENTA[estado as keyof typeof TONO_ESTADO_VENTA] ?? 'neutral';
  }

  etiquetaReserva(estado: string): string {
    return ETIQUETA_ESTADO_RESERVA[estado as keyof typeof ETIQUETA_ESTADO_RESERVA] ?? estado;
  }

  tonoReserva(estado: string): string {
    return TONO_ESTADO_RESERVA[estado as keyof typeof TONO_ESTADO_RESERVA] ?? 'neutral';
  }

  etiquetaDevolucion(estado: string): string {
    return ETIQUETA_ESTADO_DEVOLUCION[estado as keyof typeof ETIQUETA_ESTADO_DEVOLUCION] ?? estado;
  }

  tonoDevolucion(estado: string): string {
    return TONO_ESTADO_DEVOLUCION[estado as keyof typeof TONO_ESTADO_DEVOLUCION] ?? 'neutral';
  }

  whatsapp(cliente: Cliente, mensaje?: string): string {
    return enlaceWhatsApp(cliente.telefono, mensaje);
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }
}
