import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { InventarioResumen } from '../../core/models/dashboard';
import { Paginated } from '../../core/models/paginated';
import {
  CategoriaRef,
  CondicionRef,
  EstadoPrenda,
  ETIQUETA_ESTADO,
  Producto,
  TallaRef,
  TONO_ESTADO,
} from '../../core/models/producto';
import { CatalogosService } from '../../core/services/catalogos.service';
import { InventarioFiltros, InventarioService } from '../../core/services/inventario.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { ProgressComponent } from '../../shared/components/progress/progress';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../shared/components/stat-card/stat-card';
import { HistorialModalComponent } from '../productos/historial-modal';

type Pestana = 'resumen' | 'prendas' | 'sin-rotacion';

@Component({
  imports: [
    FormsModule,
    StatCardComponent,
    BadgeComponent,
    ProgressComponent,
    EmptyStateComponent,
    SpinnerComponent,
    NotificationCenterComponent,
    PaginatorComponent,
    ModalComponent,
    ConfirmDialogComponent,
    HistorialModalComponent,
  ],
  selector: 'app-inventario',
  standalone: true,
  styleUrl: './inventario.scss',
  templateUrl: './inventario.html',
})
export class InventarioComponent implements OnInit {
  private readonly inventarioService = inject(InventarioService);
  private readonly productosService = inject(ProductosService);
  private readonly catalogosService = inject(CatalogosService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);

  readonly pestana = signal<Pestana>('resumen');

  readonly datos = signal<InventarioResumen | null>(null);
  readonly cargando = signal(true);
  error: string | null = null;

  readonly categorias = signal<CategoriaRef[]>([]);
  readonly tallas = signal<TallaRef[]>([]);
  readonly condiciones = signal<CondicionRef[]>([]);

  readonly filtros = signal({
    busqueda: '',
    estado: '' as '' | EstadoPrenda,
    categoria: null as number | null,
    talla: null as number | null,
    condicion: null as number | null,
    publicado: '' as '' | '1' | '0',
    verificada: '' as '' | '1' | '0',
    dias_min: null as number | null,
    dias_max: null as number | null,
    orden: 'antiguedad',
  });

  readonly pagina = signal(1);
  readonly perPage = signal(20);
  readonly resultados = signal<Paginated<Producto> | null>(null);
  readonly cargandoListado = signal(false);

  readonly umbral = signal(30);
  readonly paginaSinRotacion = signal(1);
  readonly resultadosSinRotacion = signal<Paginated<Producto> | null>(null);
  readonly cargandoSinRotacion = signal(false);

  private readonly umbrales = [30, 60, 90];

  readonly verificarObjetivo = signal<{ producto: Producto; valor: boolean } | null>(null);

  readonly modalPrecio = signal<Producto | null>(null);
  precioNuevo: number | null = null;
  guardandoPrecio = false;

  readonly modalHistorial = signal<Producto | null>(null);

  revisionAbierta = false;
  categoriaRevision: number | null = null;
  ejecutandoRevision = false;

  exportando = false;

  ngOnInit(): void {
    this.cargarCatalogos();
    this.cargar();
  }

  cambiarPestana(pestana: Pestana): void {
    this.pestana.set(pestana);

    if (pestana === 'prendas' && !this.resultados()) {
      this.cargarListado();
    }

    if (pestana === 'sin-rotacion' && !this.resultadosSinRotacion()) {
      this.cargarSinRotacion();
    }
  }

  // --- Resumen ---

  crearOferta(): void {
    this.router.navigate(['/ofertas'], {
      queryParams: { crear: '1', sin_rotacion: this.umbral() },
    });
  }

  cargar(): void {
    this.cargando.set(true);
    this.error = null;

    this.inventarioService.resumen().subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.datos.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.error = err.message ?? 'No se pudo cargar el inventario.';
      },
    });
  }

  private cargarCatalogos(): void {
    this.catalogosService.categorias().subscribe({
      next: (res) => res.success && res.data && this.categorias.set(res.data),
      error: () => undefined,
    });

    this.catalogosService.tallas().subscribe({
      next: (res) => res.success && res.data && this.tallas.set(res.data),
      error: () => undefined,
    });

    this.catalogosService.condiciones().subscribe({
      next: (res) => res.success && res.data && this.condiciones.set(res.data),
      error: () => undefined,
    });
  }

  // --- Prendas ---

  filtrosServicio(): InventarioFiltros {
    const f = this.filtros();
    return {
      busqueda: f.busqueda || undefined,
      estado: f.estado || null,
      categoria: f.categoria,
      talla: f.talla,
      condicion: f.condicion,
      publicado: f.publicado === '' ? null : f.publicado === '1',
      verificada: f.verificada === '' ? null : f.verificada === '1',
      dias_min: f.dias_min,
      dias_max: f.dias_max,
      orden: f.orden,
    };
  }

  cargarListado(): void {
    this.cargandoListado.set(true);

    this.inventarioService
      .listado({ ...this.filtrosServicio(), page: this.pagina(), per_page: this.perPage() })
      .subscribe({
        next: (res) => {
          this.cargandoListado.set(false);
          if (res.success && res.data) {
            this.resultados.set(res.data);
          }
        },
        error: (err) => {
          this.cargandoListado.set(false);
          this.toast.error(err.message ?? 'No se pudo cargar el listado de inventario.');
        },
      });
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargarListado();
  }

  limpiarFiltros(): void {
    this.filtros.set({
      busqueda: '',
      estado: '',
      categoria: null,
      talla: null,
      condicion: null,
      publicado: '',
      verificada: '',
      dias_min: null,
      dias_max: null,
      orden: 'antiguedad',
    });
    this.onBuscar();
  }

  onPagina(page: number): void {
    this.pagina.set(page);
    this.cargarListado();
  }

  onPerPage(perPage: number): void {
    this.perPage.set(perPage);
    this.onBuscar();
  }

  // --- Sin rotación ---

  cargarSinRotacion(): void {
    this.cargandoSinRotacion.set(true);

    this.inventarioService.sinRotacion(this.umbral(), this.paginaSinRotacion(), this.perPage()).subscribe({
      next: (res) => {
        this.cargandoSinRotacion.set(false);
        if (res.success && res.data) {
          this.resultadosSinRotacion.set(res.data);
        }
      },
      error: (err) => {
        this.cargandoSinRotacion.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar las prendas sin rotación.');
      },
    });
  }

  verUmbral(dias: number): void {
    this.umbral.set(dias);
    this.paginaSinRotacion.set(1);
    this.cargarSinRotacion();
  }

  onPaginaSinRotacion(page: number): void {
    this.paginaSinRotacion.set(page);
    this.cargarSinRotacion();
  }

  irASinRotacion(dias: number): void {
    this.pestana.set('sin-rotacion');
    this.verUmbral(dias);
  }

  // --- Verificación ---

  solicitarVerificar(producto: Producto): void {
    this.verificarObjetivo.set({ producto, valor: !producto.verificada });
  }

  confirmarVerificar(): void {
    const objetivo = this.verificarObjetivo();
    if (!objetivo) {
      return;
    }
    this.verificarObjetivo.set(null);
    this.ejecutarVerificar(objetivo.producto, objetivo.valor);
  }

  private ejecutarVerificar(producto: Producto, valor: boolean): void {
    this.inventarioService.verificar(producto.id, valor).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(valor ? 'Prenda verificada.' : 'Verificación revertida.');
          this.refrescarListados();
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudo actualizar la verificación.'),
    });
  }

  // --- Revisión física ---

  abrirRevision(): void {
    this.categoriaRevision = null;
    this.revisionAbierta = true;
  }

  ejecutarRevision(): void {
    this.ejecutandoRevision = true;

    this.inventarioService.revision(this.categoriaRevision ?? undefined).subscribe({
      next: (res) => {
        this.ejecutandoRevision = false;
        this.revisionAbierta = false;
        if (res.success) {
          this.toast.success(res.message ?? 'Revisión física iniciada.');
          this.refrescarListados();
          this.cargar();
        }
      },
      error: (err) => {
        this.ejecutandoRevision = false;
        this.toast.error(err.message ?? 'No se pudo iniciar la revisión.');
      },
    });
  }

  // --- Precio / publicación ---

  abrirPrecio(producto: Producto): void {
    this.precioNuevo = Number(producto.precio);
    this.modalPrecio.set(producto);
  }

  cerrarPrecio(): void {
    this.modalPrecio.set(null);
    this.precioNuevo = null;
  }

  guardarPrecio(): void {
    const producto = this.modalPrecio();
    if (!producto || this.precioNuevo == null || this.precioNuevo < 0) {
      return;
    }

    this.guardandoPrecio = true;

    this.productosService.actualizar(producto.id, { precio: this.precioNuevo }).subscribe({
      next: (res) => {
        this.guardandoPrecio = false;
        if (res.success) {
          this.toast.success('Precio actualizado.');
          this.cerrarPrecio();
          this.refrescarListados();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardandoPrecio = false;
        this.toast.error(err.message ?? 'No se pudo actualizar el precio.');
      },
    });
  }

  cambiarPublicado(producto: Producto): void {
    this.productosService.actualizar(producto.id, { publicado: !producto.publicado }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(producto.publicado ? 'Prenda ocultada de la tienda.' : 'Prenda publicada.');
          this.refrescarListados();
          this.cargar();
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudo cambiar la publicación.'),
    });
  }

  abrirHistorial(producto: Producto): void {
    this.modalHistorial.set(producto);
  }

  cerrarHistorial(): void {
    this.modalHistorial.set(null);
  }

  private refrescarListados(): void {
    if (this.resultados()) {
      this.cargarListado();
    }
    if (this.resultadosSinRotacion()) {
      this.cargarSinRotacion();
    }
  }

  // --- Exportar ---

  exportar(): void {
    this.exportando = true;

    this.inventarioService.exportar(this.filtrosServicio()).subscribe({
      next: (blob) => {
        this.exportando = false;
        this.descargarBlob(blob, 'everly_inventario.csv');
      },
      error: (err) => {
        this.exportando = false;
        this.toast.error(err.message ?? 'No se pudo exportar el inventario.');
      },
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

  // --- Helpers ---

  umbralesDisponibles(): number[] {
    return this.umbrales;
  }

  valorPorEstado(estado: string): number {
    return this.datos()?.por_estado.find((item) => item.estado === estado)?.valor ?? 0;
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO] ?? estado;
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as keyof typeof TONO_ESTADO] ?? 'neutral';
  }

  totalPorEstado(estado: string): number {
    return this.datos()?.por_estado.find((item) => item.estado === estado)?.total ?? 0;
  }

  suma(lista: { total: number }[]): number {
    return lista.reduce((acc, item) => acc + item.total, 0);
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }
}
