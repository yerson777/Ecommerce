import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CategoriaRef, Producto } from '../../core/models/producto';
import { MetodoPagoRef } from '../../core/models/pago';
import { MetodoEntregaConfig } from '../../core/models/configuracion';
import {
  ClienteOpcion,
  ETIQUETA_ESTADO_VENTA,
  EstadoVenta,
  TONO_ESTADO_VENTA,
  Venta,
} from '../../core/models/venta';
import { Paginated } from '../../core/models/paginated';
import { ApiResponse } from '../../core/models/api-response';
import { CatalogosService } from '../../core/services/catalogos.service';
import { ClientesService } from '../../core/services/clientes.service';
import { ConfiguracionService } from '../../core/services/configuracion.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { VentasService } from '../../core/services/ventas.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { VentaPresencialModalComponent } from './venta-presencial-modal';

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
    VentaPresencialModalComponent,
  ],
  selector: 'app-ventas',
  standalone: true,
  styleUrl: './ventas.scss',
  templateUrl: './ventas.html',
})
export class VentasComponent implements OnInit {
  private readonly ventasService = inject(VentasService);
  private readonly catalogosService = inject(CatalogosService);
  private readonly productosService = inject(ProductosService);
  private readonly clientesService = inject(ClientesService);
  private readonly configuracionService = inject(ConfiguracionService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({
    busqueda: '',
    estado: '' as '' | EstadoVenta,
    categoria: null as number | null,
    producto: null as number | null,
    cliente: null as number | null,
    metodo_pago_id: null as number | null,
    fecha_desde: '',
    fecha_hasta: '',
  });

  readonly resultados = signal<Paginated<Venta> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly categorias = signal<CategoriaRef[]>([]);
  readonly productosOpciones = signal<Producto[]>([]);
  readonly clientes = signal<ClienteOpcion[]>([]);
  readonly metodosPago = signal<MetodoPagoRef[]>([]);
  readonly metodosEntrega = signal<MetodoEntregaConfig[]>([]);

  readonly detalle = signal<Venta | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);

  readonly registrando = signal(false);

  readonly anulando = signal<Venta | null>(null);
  readonly anulandoProceso = signal(false);

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
    this.filtros.set({
      busqueda: '',
      estado: '',
      categoria: null,
      producto: null,
      cliente: null,
      metodo_pago_id: null,
      fecha_desde: '',
      fecha_hasta: '',
    });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  private cargarCatalogos(): void {
    this.catalogosService.categorias().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.categorias.set(res.data);
        }
      },
      error: () => undefined,
    });

    this.productosService.listar({ per_page: 200, page: 1 }).subscribe({
      next: (res: ApiResponse<Paginated<Producto>>) => {
        if (res.success && res.data) {
          this.productosOpciones.set(res.data.data);
        }
      },
      error: () => undefined,
    });

    this.clientesService.opciones().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.clientes.set(res.data);
        }
      },
      error: () => undefined,
    });

    this.ventasService.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosPago.set(res.data);
        }
      },
      error: () => undefined,
    });

    this.configuracionService.metodosEntrega().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodosEntrega.set(res.data);
        }
      },
      error: () => undefined,
    });
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.ventasService
      .listar({
        busqueda: f.busqueda || undefined,
        estado: f.estado || null,
        categoria_id: f.categoria,
        producto_id: f.producto,
        cliente_id: f.cliente,
        metodo_pago_id: f.metodo_pago_id,
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
          this.toast.error(err.message ?? 'No se pudieron cargar las ventas.');
        },
      });
  }

  verDetalle(venta: Venta): void {
    this.detalleAbierto.set(true);
    this.detalleCargando.set(true);
    this.detalle.set(null);

    this.ventasService.detalle(venta.id).subscribe({
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

  abrirRegistro(): void {
    this.registrando.set(true);
  }

  cerrarRegistro(): void {
    this.registrando.set(false);
  }

  onRegistrada(venta: Venta): void {
    this.registrando.set(false);
    this.cargar();
    this.verDetalle(venta);
  }

  pedirAnulacion(): void {
    const detalle = this.detalle();
    if (detalle) {
      this.anulando.set(detalle);
    }
  }

  cancelarAnulacion(): void {
    this.anulando.set(null);
  }

  confirmarAnulacion(): void {
    const objetivo = this.anulando();
    if (!objetivo || this.anulandoProceso()) {
      return;
    }

    this.anulandoProceso.set(true);
    this.ventasService.anular(objetivo.id).subscribe({
      next: () => {
        this.anulandoProceso.set(false);
        this.anulando.set(null);
        this.toast.success('Venta anulada: prendas liberadas y pagos reembolsados.');
        this.cerrarDetalle();
        this.cargar();
      },
      error: (err) => {
        this.anulandoProceso.set(false);
        this.toast.error(err.message ?? 'No se pudo anular la venta.');
      },
    });
  }

  imprimirComprobante(): void {
    window.print();
  }

  puedeImprimir(): boolean {
    return !!this.detalle();
  }

  nombreProducto(productoId: number): string {
    return this.productosOpciones().find((p) => p.id === productoId)?.nombre ?? 'Prenda';
  }

  nombreCliente(id: number | null | undefined): string {
    if (!id) {
      return '—';
    }
    return this.clientes().find((c) => c.id === id)?.nombre ?? '—';
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO_VENTA[estado as keyof typeof ETIQUETA_ESTADO_VENTA] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO_VENTA[estado as keyof typeof TONO_ESTADO_VENTA] ?? 'neutral';
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  descuentoPositivo(descuento: string | number | null | undefined): boolean {
    return parseFloat(String(descuento ?? '0')) > 0;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }

  formatearFechaHora(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    return new Date(fecha).toLocaleString('es-AR');
  }
}