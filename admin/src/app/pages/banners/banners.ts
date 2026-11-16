import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { ApiError } from '../../core/models/api-response';
import {
  Banner,
  BannerPayload,
  ETIQUETA_ENLACE,
  MEDIDAS_BANNER,
  TIPOS_ENLACE_BANNER,
  TipoEnlaceBanner,
  UbicacionBanner,
} from '../../core/models/banner';
import { CategoriaRef, Producto } from '../../core/models/producto';
import { Oferta } from '../../core/models/oferta';
import { BannersService } from '../../core/services/banners.service';
import { CatalogosService } from '../../core/services/catalogos.service';
import { InventarioService } from '../../core/services/inventario.service';
import { OfertasService } from '../../core/services/ofertas.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface Formulario {
  titulo: string;
  subtitulo: string;
  enlace_tipo: TipoEnlaceBanner;
  enlace_id: number | null;
  enlace_url: string;
  fecha_inicio: string;
  fecha_fin: string;
  activo: boolean;
  archivo: File | null;
}

function formularioVacio(): Formulario {
  return {
    titulo: '',
    subtitulo: '',
    enlace_tipo: 'ninguno',
    enlace_id: null,
    enlace_url: '',
    fecha_inicio: '',
    fecha_fin: '',
    activo: true,
    archivo: null,
  };
}

function aLocalInput(iso: string | null | undefined): string {
  if (!iso) {
    return '';
  }
  const fecha = new Date(iso);
  if (Number.isNaN(fecha.getTime())) {
    return '';
  }
  const pad = (n: number): string => String(n).padStart(2, '0');
  return (
    `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}` +
    `T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}`
  );
}

@Component({
  imports: [
    FormsModule,
    DatePipe,
    BadgeComponent,
    ConfirmDialogComponent,
    EmptyStateComponent,
    ModalComponent,
    NotificationCenterComponent,
    SpinnerComponent,
  ],
  selector: 'app-banners',
  standalone: true,
  styleUrl: './banners.scss',
  templateUrl: './banners.html',
})
export class BannersComponent implements OnInit {
  private readonly bannersService = inject(BannersService);
  private readonly catalogos = inject(CatalogosService);
  private readonly inventario = inject(InventarioService);
  private readonly ofertasService = inject(OfertasService);
  private readonly productosService = inject(ProductosService);
  private readonly toast = inject(ToastService);

  readonly etiquetasEnlace = ETIQUETA_ENLACE;
  readonly tiposEnlace = TIPOS_ENLACE_BANNER;

  private readonly route = inject(ActivatedRoute);

  private readonly ubicacionSignal = signal<UbicacionBanner>(
    (this.route.snapshot.data['ubicacion'] as UbicacionBanner | undefined) ?? 'inicio',
  );
  readonly ubicacion = this.ubicacionSignal.asReadonly();
  readonly medida = computed(() => MEDIDAS_BANNER[this.ubicacionSignal()]);
  readonly esInicio = computed(() => this.ubicacionSignal() === 'inicio');
  readonly previewArchivo = signal<string | null>(null);
  readonly textoMedida = computed(() => {
    const m = this.medida();
    return this.esInicio()
      ? `Tamaño recomendado: ${m.ancho}×${m.alto} px (proporción 5:4)`
      : `Medida recomendada: ${m.ancho}×${m.alto} px (proporción 21:9)`;
  });
  readonly avisoMedida = signal<{ tipo: 'success' | 'warning' | 'danger'; icono: string; texto: string } | null>(null);

  readonly banners = signal<Banner[]>([]);
  readonly cargando = signal(true);

  readonly categorias = signal<CategoriaRef[]>([]);
  readonly ofertas = signal<Oferta[]>([]);

  readonly modalAbierto = signal(false);
  readonly editando = signal<Banner | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');

  form = formularioVacio();

  readonly productoDestino = signal<Producto | null>(null);
  readonly busquedaProducto = signal('');
  readonly resultadosBusqueda = signal<Producto[]>([]);
  readonly buscandoProducto = signal(false);

  eliminarObjetivo: Banner | null = null;
  reordenandoId: number | null = null;

  dragIndex: number | null = null;

  ngOnInit(): void {
    this.route.data.subscribe(({ ubicacion }) => {
      const u = (ubicacion as UbicacionBanner | undefined) ?? 'inicio';
      this.ubicacionSignal.set(u);
      this.cargar();
    });
    this.catalogos.categorias().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.categorias.set(res.data);
        }
      },
    });
    this.ofertasService.listar().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.ofertas.set(res.data);
        }
      },
    });
  }

  bannersOrdenados(): Banner[] {
    return [...this.banners()].sort((a, b) => a.orden - b.orden);
  }

  cargar(): void {
    this.cargando.set(true);

    this.bannersService.listar(this.ubicacion()).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.banners.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar los banners.');
      },
    });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.cargarFormularioBanner(null);
    this.modalAbierto.set(true);
  }

  abrirEditar(banner: Banner): void {
    this.editando.set(banner);
    this.cargarFormularioBanner(banner);
    this.modalAbierto.set(true);

    if (banner.enlace_tipo === 'producto' && banner.enlace_id) {
      this.cargarProductoDestino(banner.enlace_id);
    }

    if (banner.url) {
      this.verificarProporcion(banner.url);
    }
  }

  cerrarModal(): void {
    this.modalAbierto.set(false);
    this.editando.set(null);
    this.productoDestino.set(null);
    this.resultadosBusqueda.set([]);
    this.busquedaProducto.set('');
    this.previewArchivo.set(null);
    this.avisoMedida.set(null);
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    const previo = this.previewArchivo();
    if (previo) {
      URL.revokeObjectURL(previo);
    }
    this.form.archivo = archivo;
    this.previewArchivo.set(archivo ? URL.createObjectURL(archivo) : null);
    input.value = '';

    if (archivo) {
      this.verificarProporcion(this.previewArchivo()!);
    }
  }

  private verificarProporcion(url: string): void {
    const imagen = new Image();
    imagen.onload = () => {
      const m = this.medida();
      const esperada = m.ancho / m.alto;
      const real = imagen.naturalWidth / imagen.naturalHeight;
      const proporcionCorrecta = Math.abs(real - esperada) / esperada <= 0.05;
      const anchoMinimo = m.ancho * 0.85;
      const anchoMedio = m.ancho * 0.5;
      const w = imagen.naturalWidth;
      const h = imagen.naturalHeight;

      if (!proporcionCorrecta) {
        this.avisoMedida.set({
          tipo: 'warning',
          icono: '⚠',
          texto: `La imagen se recortará un poco en la tienda (${w}×${h} px). Recomendado: ${m.ancho}×${m.alto} px (proporción ${m.proporcion})`,
        });
      } else if (w >= anchoMinimo) {
        this.avisoMedida.set({
          tipo: 'success',
          icono: '✓',
          texto: `✓ Medida correcta (${w}×${h} px)`,
        });
      } else if (w >= anchoMedio) {
        this.avisoMedida.set({
          tipo: 'warning',
          icono: '⚠',
          texto: `La imagen es algo pequeña (${w}×${h} px). Podría verse menos nítida. Recomendado: ${m.ancho}×${m.alto} px`,
        });
      } else {
        this.avisoMedida.set({
          tipo: 'danger',
          icono: '⚠',
          texto: `Imagen demasiado pequeña (${w}×${h} px). Se verá borrosa. Recomendado: ${m.ancho}×${m.alto} px`,
        });
      }
    };
    imagen.onerror = () => {
      this.avisoMedida.set(null);
    };
    imagen.src = url;
  }

  vistaPreviaEscritorio(): string | null {
    return this.previewArchivo() ?? this.editando()?.url ?? null;
  }

  buscarProductoDestino(): void {
    const termino = this.busquedaProducto().trim();
    if (!termino) {
      this.resultadosBusqueda.set([]);
      return;
    }

    this.buscandoProducto.set(true);
    this.inventario
      .listado({ busqueda: termino, per_page: 8 })
      .subscribe({
        next: (res) => {
          this.buscandoProducto.set(false);
          this.resultadosBusqueda.set(res.success && res.data ? res.data.data : []);
        },
        error: () => {
          this.buscandoProducto.set(false);
          this.resultadosBusqueda.set([]);
        },
      });
  }

  elegirProductoDestino(producto: Producto): void {
    this.form.enlace_id = producto.id;
    this.productoDestino.set(producto);
    this.resultadosBusqueda.set([]);
    this.busquedaProducto.set('');
    this.errorFormulario.set('');
  }

  limpiarDestino(): void {
    this.form.enlace_id = null;
    this.productoDestino.set(null);
  }

  onTipoEnlaceCambiado(): void {
    this.form.enlace_id = null;
    this.productoDestino.set(null);
    this.errorFormulario.set('');
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    if (!this.editando() && !this.form.archivo) {
      this.errorFormulario.set('Debe adjuntar una imagen para crear el banner.');
      return;
    }

    const tipo = this.form.enlace_tipo;
    if (tipo === 'producto' && !this.form.enlace_id) {
      this.errorFormulario.set('Seleccioná la prenda de destino.');
      return;
    }
    if (tipo === 'categoria' && !this.form.enlace_id) {
      this.errorFormulario.set('Seleccioná la categoría de destino.');
      return;
    }
    if (tipo === 'oferta' && !this.form.enlace_id) {
      this.errorFormulario.set('Seleccioná la oferta de destino.');
      return;
    }
    if (tipo === 'url' && !this.form.enlace_url.trim()) {
      this.errorFormulario.set('Indicá la URL de destino.');
      return;
    }
    if (
      this.form.fecha_inicio &&
      this.form.fecha_fin &&
      new Date(this.form.fecha_fin) <= new Date(this.form.fecha_inicio)
    ) {
      this.errorFormulario.set('El fin debe ser posterior al inicio.');
      return;
    }

    const datos: BannerPayload = {
      titulo: this.form.titulo || null,
      subtitulo: this.form.subtitulo || null,
      enlace_tipo: tipo,
      enlace_id: tipo === 'url' || tipo === 'ninguno' ? null : this.form.enlace_id,
      enlace_url: tipo === 'url' ? this.form.enlace_url.trim() || null : null,
      fecha_inicio: this.form.fecha_inicio || null,
      fecha_fin: this.form.fecha_fin || null,
      activo: this.form.activo,
      ubicacion: this.ubicacion(),
    };

    this.errorFormulario.set('');
    this.guardando.set(true);

    const editando = this.editando();
    const peticion = editando
      ? this.bannersService.actualizar(editando.id, this.form.archivo, datos)
      : this.bannersService.crear(this.form.archivo as File, datos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(editando ? 'Banner actualizado.' : 'Banner creado.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        let mensaje = err.message ?? 'No se pudo guardar el banner.';
        if (err.errors && typeof err.errors === 'object') {
          const primera = Object.values(err.errors).flat()[0];
          if (typeof primera === 'string') {
            mensaje = primera;
          }
        }
        this.errorFormulario.set(mensaje);
      },
    });
  }

  mover(indice: number, delta: number): void {
    const lista = this.bannersOrdenados();
    const objetivo = indice + delta;

    if (objetivo < 0 || objetivo >= lista.length) {
      return;
    }

    const aux = lista[indice];
    lista[indice] = lista[objetivo];
    lista[objetivo] = aux;

    this.reordenar(lista.map((banner) => banner.id));
  }

  onDragStart(indice: number): void {
    this.dragIndex = indice;
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
  }

  onDrop(event: DragEvent, destino: number): void {
    event.preventDefault();
    const origen = this.dragIndex;
    this.dragIndex = null;

    if (origen === null || origen === destino || this.reordenandoId !== null) {
      return;
    }

    const lista = this.bannersOrdenados();
    const [movido] = lista.splice(origen, 1);
    lista.splice(destino, 0, movido);

    this.reordenar(lista.map((banner) => banner.id));
  }

  reordenar(ordenes: number[]): void {
    this.reordenandoId = null;

    this.bannersService.reordenar(ordenes, this.ubicacion()).subscribe({
      next: (res) => {
        this.reordenandoId = null;
        if (res.success && res.data) {
          this.banners.set(res.data);
        }
        this.toast.success('Orden de banners actualizado.');
      },
      error: (err) => {
        this.reordenandoId = null;
        this.toast.error(err.message ?? 'No se pudo cambiar el orden.');
        this.cargar();
      },
    });
  }

  pedirEliminar(banner: Banner): void {
    this.eliminarObjetivo = banner;
  }

  confirmarEliminar(): void {
    const banner = this.eliminarObjetivo;
    if (!banner) {
      return;
    }

    this.bannersService.eliminar(banner.id).subscribe({
      next: () => {
        this.eliminarObjetivo = null;
        this.toast.success('Banner eliminado.');
        this.cargar();
      },
      error: (err) => {
        this.eliminarObjetivo = null;
        this.toast.error(err.message ?? 'No se pudo eliminar el banner.');
      },
    });
  }

  textoVigencia(banner: Banner): string {
    const inicio = banner.fecha_inicio ? aLocalInput(banner.fecha_inicio).replace('T', ' ') : null;
    const fin = banner.fecha_fin ? aLocalInput(banner.fecha_fin).replace('T', ' ') : null;

    if (inicio && fin) {
      return `${inicio} → ${fin}`;
    }
    if (inicio) {
      return `Desde ${inicio}`;
    }
    if (fin) {
      return `Hasta ${fin}`;
    }
    return 'Siempre';
  }

  nombreOferta(id: number | null): string {
    if (!id) {
      return '';
    }
    return this.ofertas().find((o) => o.id === id)?.nombre ?? `#${id}`;
  }

  nombreCategoria(id: number | null): string {
    if (!id) {
      return '';
    }
    return this.categorias().find((c) => c.id === id)?.nombre ?? `#${id}`;
  }

  private cargarProductoDestino(id: number): void {
    this.productosService.detalle(id).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.productoDestino.set(res.data);
        }
      },
      error: () => {
        this.productoDestino.set(null);
      },
    });
  }

  private cargarFormularioBanner(banner: Banner | null): void {
    this.form = {
      titulo: banner?.titulo ?? '',
      subtitulo: banner?.subtitulo ?? '',
      enlace_tipo: banner?.enlace_tipo ?? 'ninguno',
      enlace_id: banner?.enlace_id ?? null,
      enlace_url: banner?.enlace_tipo === 'url' ? (banner?.enlace ?? '') : '',
      fecha_inicio: aLocalInput(banner?.fecha_inicio),
      fecha_fin: aLocalInput(banner?.fecha_fin),
      activo: banner?.activo ?? true,
      archivo: null,
    };
    this.previewArchivo.set(null);
    this.avisoMedida.set(null);
    this.errorFormulario.set('');
  }
}
