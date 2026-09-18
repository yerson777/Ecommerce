import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  CategoriaRef,
  ETIQUETA_ESTADO,
  EstadoPrenda,
  Producto,
  TONO_ESTADO,
  TallaRef,
} from '../../core/models/producto';
import { Paginated } from '../../core/models/paginated';
import { CatalogosService } from '../../core/services/catalogos.service';
import { InventarioService } from '../../core/services/inventario.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { EstadoModalComponent } from './estado-modal';
import { HistorialModalComponent } from './historial-modal';
import { ImagenesModalComponent } from './imagenes-modal';
import { ProductoFormModalComponent } from './producto-form-modal';

interface ModalFormulario {
  abierto: boolean;
  producto: Producto | null;
  readonly: boolean;
}

interface ModalEstado {
  abierto: boolean;
  producto: Producto | null;
  tipo: 'reservar' | 'vender';
}

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    ConfirmDialogComponent,
    EmptyStateComponent,
    PaginatorComponent,
    SpinnerComponent,
    ProductoFormModalComponent,
    EstadoModalComponent,
    HistorialModalComponent,
    ImagenesModalComponent,
  ],
  selector: 'app-productos',
  standalone: true,
  styleUrl: './productos.scss',
  templateUrl: './productos.html',
})
export class ProductosComponent implements OnInit {
  private readonly productosService = inject(ProductosService);
  private readonly catalogosService = inject(CatalogosService);
  private readonly inventarioService = inject(InventarioService);
  private readonly toast = inject(ToastService);

  readonly filtros = signal({
    busqueda: '',
    categoria: null as number | null,
    talla: null as number | null,
    estado: '' as '' | EstadoPrenda,
    publicado: '' as '' | '1' | '0',
    precio_min: null as number | null,
    precio_max: null as number | null,
  });

  readonly pagina = signal(1);
  readonly perPage = signal(15);
  readonly resultados = signal<Paginated<Producto> | null>(null);
  readonly cargando = signal(true);

  readonly categorias = signal<CategoriaRef[]>([]);
  readonly tallas = signal<TallaRef[]>([]);

  readonly modalFormulario = signal<ModalFormulario>({ abierto: false, producto: null, readonly: false });
  readonly modalImagenes = signal<Producto | null>(null);
  readonly modalEstado = signal<ModalEstado>({ abierto: false, producto: null, tipo: 'reservar' });
  readonly modalHistorial = signal<Producto | null>(null);

  eliminarObjetivo: Producto | null = null;
  liberarObjetivo: Producto | null = null;

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
      categoria: null,
      talla: null,
      estado: '',
      publicado: '',
      precio_min: null,
      precio_max: null,
    });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  onPerPage(cantidad: number): void {
    this.perPage.set(cantidad);
    this.onBuscar();
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

    this.catalogosService.tallas().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.tallas.set(res.data);
        }
      },
      error: () => undefined,
    });
  }

  cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.productosService
      .listar({
        busqueda: f.busqueda || undefined,
        categoria: f.categoria,
        talla: f.talla,
        estado: f.estado || null,
        publicado: f.publicado === '' ? null : f.publicado === '1',
        precio_min: f.precio_min,
        precio_max: f.precio_max,
        page: this.pagina(),
        per_page: this.perPage(),
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
          this.toast.error(err.message ?? 'No se pudieron cargar los productos.');
        },
      });
  }

  nuevoProducto(): void {
    this.modalFormulario.set({ abierto: true, producto: null, readonly: false });
  }

  abrirEdicion(producto: Producto): void {
    this.modalFormulario.set({ abierto: true, producto, readonly: false });
  }

  abrirLectura(producto: Producto): void {
    this.modalFormulario.set({ abierto: true, producto, readonly: true });
  }

  cerrarFormulario(): void {
    this.modalFormulario.set({ abierto: false, producto: null, readonly: false });
  }

  abrirImagenes(producto: Producto): void {
    this.modalImagenes.set(producto);
  }

  cerrarImagenes(): void {
    this.modalImagenes.set(null);
  }

  abrirEstado(producto: Producto, tipo: 'reservar' | 'vender'): void {
    this.modalEstado.set({ abierto: true, producto, tipo });
  }

  cerrarEstado(): void {
    this.modalEstado.set({ abierto: false, producto: null, tipo: 'reservar' });
  }

  abrirHistorial(producto: Producto): void {
    this.modalHistorial.set(producto);
  }

  cerrarHistorial(): void {
    this.modalHistorial.set(null);
  }

  cambiarPublicado(producto: Producto): void {
    this.productosService.actualizar(producto.id, { publicado: !producto.publicado }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(producto.publicado ? 'Prenda despublicada.' : 'Prenda publicada.');
          this.cargar();
        }
      },
      error: (err) => {
        this.toast.error(err.message ?? 'No se pudo cambiar la publicación.');
      },
    });
  }

  solicitarEliminar(producto: Producto): void {
    this.eliminarObjetivo = producto;
  }

  confirmarEliminar(): void {
    const producto = this.eliminarObjetivo;
    if (!producto) {
      return;
    }

    this.productosService.eliminar(producto.id).subscribe({
      next: (res) => {
        this.eliminarObjetivo = null;
        if (res.success) {
          this.toast.success('Producto eliminado.');
          this.cargar();
        }
      },
      error: (err) => {
        this.eliminarObjetivo = null;
        this.toast.error(err.message ?? 'No se pudo eliminar el producto.');
      },
    });
  }

  solicitarLiberar(producto: Producto): void {
    this.liberarObjetivo = producto;
  }

  confirmarLiberar(): void {
    const producto = this.liberarObjetivo;
    if (!producto) {
      return;
    }

    this.liberarObjetivo = null;

    this.inventarioService.liberar(producto.id).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success('Reserva liberada y prenda disponible.');
          this.cargar();
        }
      },
      error: (err) => {
        this.toast.error(err.message ?? 'No se pudo liberar la reserva.');
      },
    });
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as keyof typeof TONO_ESTADO] ?? 'neutral';
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}