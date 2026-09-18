import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CategoriaRef, Producto } from '../../core/models/producto';
import {
  ETIQUETA_ESTADO_VENTA,
  EstadoVenta,
  TONO_ESTADO_VENTA,
  Venta,
} from '../../core/models/venta';
import { Paginated } from '../../core/models/paginated';
import { ApiResponse } from '../../core/models/api-response';
import { CatalogosService } from '../../core/services/catalogos.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { VentasService } from '../../core/services/ventas.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    EmptyStateComponent,
    ModalComponent,
    PaginatorComponent,
    SpinnerComponent,
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
  private readonly toast = inject(ToastService);

  readonly filtros = signal({
    busqueda: '',
    estado: '' as '' | EstadoVenta,
    categoria: null as number | null,
    producto: null as number | null,
    fecha_desde: '',
    fecha_hasta: '',
  });

  readonly resultados = signal<Paginated<Venta> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);

  readonly categorias = signal<CategoriaRef[]>([]);
  readonly productosOpciones = signal<Producto[]>([]);

  readonly detalle = signal<Venta | null>(null);
  readonly detalleAbierto = signal(false);
  readonly detalleCargando = signal(false);

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

  tituloDetalle(): string {
    const d = this.detalle();
    return d ? `Venta ${d.numero_venta}` : 'Cargando venta...';
  }

  nombreProducto(productoId: number): string {
    return this.productosOpciones().find((p) => p.id === productoId)?.nombre ?? 'Prenda';
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO_VENTA[estado as keyof typeof ETIQUETA_ESTADO_VENTA] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO_VENTA[estado as keyof typeof TONO_ESTADO_VENTA] ?? 'neutral';
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}