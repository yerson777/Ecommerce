import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiResponse } from '../../core/models/api-response';
import { Paginated } from '../../core/models/paginated';
import { Producto } from '../../core/models/producto';
import {
  ETIQUETA_ESTADO_RESERVA,
  Reserva,
  TONO_ESTADO_RESERVA,
} from '../../core/models/reserva';
import { ClientesService } from '../../core/services/clientes.service';
import { ProductosService } from '../../core/services/productos.service';
import { ReservasService } from '../../core/services/reservas.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface CrearEstado {
  abierto: boolean;
  guardando: boolean;
  error: string | null;
  productoId: number | null;
  clienteId: number | null;
  venceEn: string;
}

interface ConvertirEstado {
  reserva: Reserva | null;
  guardando: boolean;
  error: string | null;
  costoEnvio: number;
  notas: string;
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
  selector: 'app-reservas',
  standalone: true,
  styleUrl: './reservas.scss',
  templateUrl: './reservas.html',
})
export class ReservasComponent implements OnInit {
  private readonly reservasService = inject(ReservasService);
  private readonly productosService = inject(ProductosService);
  private readonly clientesService = inject(ClientesService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({ busqueda: '', estado: '' as string });

  readonly resultados = signal<Paginated<Reserva> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly productosDisponibles = signal<Producto[]>([]);
  readonly clientes = signal<{ id: number; nombre: string; telefono: string | null }[]>([]);
  readonly cargandoCatalogos = signal(false);

  readonly crear = signal<CrearEstado>({
    abierto: false,
    guardando: false,
    error: null,
    productoId: null,
    clienteId: null,
    venceEn: '',
  });

  readonly convertir = signal<ConvertirEstado>({
    reserva: null,
    guardando: false,
    error: null,
    costoEnvio: 0,
    notas: '',
  });

  readonly liberarObjetivo = signal<Reserva | null>(null);
  readonly liberando = signal(false);

  readonly perPage = 15;

  ngOnInit(): void {
    this.cargarCatalogos();
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

  private cargarCatalogos(): void {
    this.cargandoCatalogos.set(true);

    this.productosService.listar({ per_page: 200, page: 1, estado: 'disponible' }).subscribe({
      next: (res: ApiResponse<Paginated<Producto>>) => {
        if (res.success && res.data) {
          this.productosDisponibles.set(res.data.data);
        }
      },
      error: () => undefined,
    });

    this.clientesService.opciones().subscribe({
      next: (res) => {
        this.cargandoCatalogos.set(false);
        if (res.success && res.data) {
          this.clientes.set(res.data);
        }
      },
      error: () => {
        this.cargandoCatalogos.set(false);
      },
    });
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.reservasService
      .listar({
        busqueda: f.busqueda || undefined,
        estado: f.estado === 'vencidas' || f.estado === '' ? undefined : f.estado,
        solo_vencidas: f.estado === 'vencidas' || undefined,
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
          this.toast.error(err.message ?? 'No se pudieron cargar las reservas.');
        },
      });
  }

  abrirCrear(): void {
    this.crear.set({
      abierto: true,
      guardando: false,
      error: null,
      productoId: null,
      clienteId: null,
      venceEn: this.fechaLimitePorDefecto(),
    });
  }

  cerrarCrear(): void {
    if (this.crear().guardando) {
      return;
    }
    this.crear.set({ ...this.crear(), abierto: false });
  }

  crearReserva(): void {
    const estado = this.crear();

    if (!estado.productoId) {
      this.crear.set({ ...estado, error: 'Selecciona la prenda a reservar.' });
      return;
    }
    if (!estado.clienteId) {
      this.crear.set({ ...estado, error: 'Selecciona el cliente.' });
      return;
    }

    this.crear.set({ ...estado, guardando: true, error: null });

    this.reservasService
      .crear({
        producto_id: estado.productoId,
        cliente_id: estado.clienteId,
        vence_en: estado.venceEn
          ? new Date(estado.venceEn).toISOString().slice(0, 19).replace('T', ' ')
          : null,
      })
      .subscribe({
        next: (res) => {
          this.crear.set({ ...this.crear(), guardando: false });
          if (res.success) {
            this.toast.success(res.message ?? 'Prenda reservada.');
            this.crear.set({ ...this.crear(), abierto: false });
            this.pagina.set(1);
            this.cargar();
            this.cargarCatalogos();
          }
        },
        error: (err) => {
          this.crear.set({ ...this.crear(), guardando: false, error: err.message ?? 'No se pudo reservar.' });
        },
      });
  }

  solicitarConvertir(reserva: Reserva): void {
    if (!this.esActiva(reserva)) {
      return;
    }
    this.convertir.set({
      reserva,
      guardando: false,
      error: null,
      costoEnvio: 0,
      notas: '',
    });
  }

  cerrarConvertir(): void {
    if (this.convertir().guardando) {
      return;
    }
    this.convertir.set({ ...this.convertir(), reserva: null });
  }

  esReservaDirecta(): boolean {
    return !this.convertir().reserva?.pedido_id;
  }

  confirmarConvertir(): void {
    const estado = this.convertir();
    const reserva = estado.reserva;

    if (!reserva || estado.guardando) {
      return;
    }

    this.convertir.set({ ...estado, guardando: true, error: null });

    this.reservasService
      .convertirEnVenta(reserva.id, {
        costo_envio: Number(estado.costoEnvio) || 0,
        notas: estado.notas.trim() || null,
      })
      .subscribe({
        next: (res) => {
          this.convertir.set({ ...this.convertir(), guardando: false });
          if (res.success) {
            this.toast.success(res.message ?? 'Venta registrada.');
            this.convertir.set({ ...this.convertir(), reserva: null });
            this.cargar();
            this.cargarCatalogos();
          }
        },
        error: (err) => {
          this.convertir.set({ ...this.convertir(), guardando: false, error: err.message ?? 'No se pudo convertir en venta.' });
        },
      });
  }

  solicitarLiberar(reserva: Reserva): void {
    if (this.esActiva(reserva)) {
      this.liberarObjetivo.set(reserva);
    }
  }

  cancelarLiberar(): void {
    this.liberarObjetivo.set(null);
  }

  confirmarLiberar(): void {
    const reserva = this.liberarObjetivo();
    if (!reserva || this.liberando()) {
      return;
    }

    this.liberando.set(true);

    this.reservasService.liberar(reserva.id).subscribe({
      next: (res) => {
        this.liberando.set(false);
        this.liberarObjetivo.set(null);
        if (res.success) {
          this.toast.success('Reserva liberada y prenda disponible.');
          this.cargar();
          this.cargarCatalogos();
        }
      },
      error: (err) => {
        this.liberando.set(false);
        this.liberarObjetivo.set(null);
        this.toast.error(err.message ?? 'No se pudo liberar la reserva.');
      },
    });
  }

  esActiva(reserva: Reserva): boolean {
    return reserva.estado === 'activa';
  }

  esVencida(reserva: Reserva): boolean {
    return reserva.estado === 'activa' && !!reserva.vencida;
  }

  etiquetaEstado(reserva: Reserva): string {
    if (this.esVencida(reserva)) {
      return 'Vencida';
    }
    return ETIQUETA_ESTADO_RESERVA[reserva.estado] ?? reserva.estado;
  }

  tonoEstado(reserva: Reserva): string {
    if (this.esVencida(reserva)) {
      return 'danger';
    }
    return TONO_ESTADO_RESERVA[reserva.estado] ?? 'neutral';
  }

  nombreCliente(reserva: Reserva): string {
    if (reserva.cliente?.nombre) {
      return reserva.cliente.nombre;
    }
    if (reserva.pedido?.numero_pedido) {
      return `Pedido ${reserva.pedido.numero_pedido}`;
    }
    return '—';
  }

  etiquetaProducto(reserva: Reserva): string {
    const producto = reserva.producto;
    if (!producto) {
      return 'Prenda';
    }
    const talla = producto.talla?.nombre ? ` · ${producto.talla.nombre}` : '';
    return `${producto.nombre}${talla}`;
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

  private fechaLimitePorDefecto(): string {
    const fecha = new Date(Date.now() + 24 * 60 * 60 * 1000);
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
  }
}