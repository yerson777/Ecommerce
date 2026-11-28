import { Component, ElementRef, inject, input, OnInit, output, signal, ViewChild } from '@angular/core';
import { formatearFecha as formatearFechaCompartida, formatearMoneda } from '../../core/utils/formato';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import {
  CategoriaRef,
  CondicionRef,
  ETIQUETA_ESTADO,
  MarcaRef,
  Producto,
  ProductoPayload,
  TallaRef,
  TONO_ESTADO,
} from '../../core/models/producto';
import { ImagenesService } from '../../core/services/imagenes.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ModalComponent } from '../../shared/components/modal/modal';

type EstadoRevision = 'correcta' | 'ligera' | 'fuerte';

interface RevisionFoto {
  ancho: number;
  alto: number;
  recorte: number;
  bajaResolucion: boolean;
  etiqueta: string;
  estado: EstadoRevision;
}

interface ImagenPendiente {
  archivo: File;
  preview: string;
  revision: RevisionFoto | null;
}

interface FormaProducto {
  codigo: string;
  nombre: string;
  categoria_id: number | null;
  talla_id: number | null;
  marca_id: number | null;
  color: string;
  condicion_id: number | null;
  medidas: string;
  descripcion: string;
  costo: number | null;
  precio: number | null;
  publicado: boolean;
  fecha_ingreso: string;
}

@Component({
  imports: [FormsModule, BadgeComponent, ModalComponent],
  selector: 'app-producto-form-modal',
  standalone: true,
  styleUrl: './producto-form-modal.scss',
  templateUrl: './producto-form-modal.html',
})
export class ProductoFormModalComponent implements OnInit {
  readonly producto = input<Producto | null>(null);
  readonly categorias = input<CategoriaRef[]>([]);
  readonly tallas = input<TallaRef[]>([]);
  readonly marcas = input<MarcaRef[]>([]);
  readonly condiciones = input<CondicionRef[]>([]);
  readonly soloLectura = input(false);

  readonly guardado = output<Producto>();
  readonly agregado = output<Producto>();
  readonly cerrado = output<void>();

  private readonly productosService = inject(ProductosService);
  private readonly imagenesService = inject(ImagenesService);
  private readonly toast = inject(ToastService);

  readonly forma: FormaProducto = {
    codigo: '',
    nombre: '',
    categoria_id: null,
    talla_id: null,
    marca_id: null,
    color: '',
    condicion_id: null,
    medidas: '',
    descripcion: '',
    costo: null,
    precio: null,
    publicado: false,
    fecha_ingreso: '',
  };

  esEdicion = false;
  readonly = false;
  precioBloqueado = false;
  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);
  readonly imagenesPendientes = signal<ImagenPendiente[]>([]);

  @ViewChild('campoCodigo') private campoCodigo?: ElementRef<HTMLInputElement>;
  @ViewChild('campoNombre') private campoNombre?: ElementRef<HTMLInputElement>;
  @ViewChild('campoCategoria') private campoCategoria?: ElementRef<HTMLSelectElement>;
  @ViewChild('campoTalla') private campoTalla?: ElementRef<HTMLSelectElement>;
  @ViewChild('campoCosto') private campoCosto?: ElementRef<HTMLInputElement>;
  @ViewChild('campoPrecio') private campoPrecio?: ElementRef<HTMLInputElement>;

  private readonly nombresCampos: Record<string, string> = {
    codigo: 'Código',
    nombre: 'Nombre',
    categoria_id: 'Categoría',
    talla_id: 'Talla',
    costo: 'Costo',
    precio: 'Precio',
  };

  private readonly ordenCampos = ['codigo', 'nombre', 'categoria_id', 'talla_id', 'costo', 'precio'];

  ngOnInit(): void {
    const actual = this.producto();

    if (actual) {
      this.esEdicion = true;
      const f = this.forma;
      f.codigo = actual.codigo;
      f.nombre = actual.nombre;
      f.categoria_id = actual.categoria?.id ?? null;
      f.talla_id = actual.talla?.id ?? null;
      f.marca_id = actual.marca?.id ?? null;
      f.color = actual.color ?? '';
      f.condicion_id = actual.condicion_id ?? actual.condicion?.id ?? null;
      f.medidas = actual.medidas ?? '';
      f.descripcion = actual.descripcion ?? '';
      f.costo = Number(actual.costo);
      f.precio = Number(actual.precio);
      f.publicado = actual.estado === 'vendida' ? false : actual.publicado;
      f.fecha_ingreso = actual.fecha_ingreso ?? '';

      this.precioBloqueado = actual.estado !== 'disponible';
    } else {
      this.forma.codigo = `EV-${Math.floor(1000 + Math.random() * 9000)}`;
      this.forma.fecha_ingreso = new Date().toISOString().slice(0, 10);
    }

    this.readonly = this.soloLectura();
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  private erroresDeCampos(): Record<string, string[]> {
    const f = this.forma;
    const errores: Record<string, string[]> = {};

    if (!f.codigo.trim()) errores['codigo'] = ['El código es obligatorio'];
    if (!f.nombre.trim()) errores['nombre'] = ['El nombre es obligatorio'];
    if (f.categoria_id == null) errores['categoria_id'] = ['Elegí una categoría'];
    if (f.talla_id == null) errores['talla_id'] = ['Elegí una talla'];
    if (f.costo == null) errores['costo'] = ['El costo es obligatorio'];
    else if (f.costo <= 0) errores['costo'] = ['Ingresá un monto mayor a 0'];
    if (f.precio == null) errores['precio'] = ['El precio es obligatorio'];
    else if (f.precio <= 0) errores['precio'] = ['Ingresá un monto mayor a 0'];

    return errores;
  }

  private resumenErrores(errores: Record<string, string[]>): string {
    const nombres = this.ordenCampos
      .filter((campo) => errores[campo])
      .map((campo) => this.nombresCampos[campo]);
    return `Falta completar: ${nombres.join(', ')}.`;
  }

  private obtenerCampo(campo: string): HTMLElement | undefined {
    switch (campo) {
      case 'codigo': return this.campoCodigo?.nativeElement;
      case 'nombre': return this.campoNombre?.nativeElement;
      case 'categoria_id': return this.campoCategoria?.nativeElement;
      case 'talla_id': return this.campoTalla?.nativeElement;
      case 'costo': return this.campoCosto?.nativeElement;
      case 'precio': return this.campoPrecio?.nativeElement;
      default: return undefined;
    }
  }

  private enfocarPrimerError(errores: Record<string, string[]>): void {
    const primero = this.ordenCampos.find((campo) => errores[campo]);
    if (!primero) return;

    const objetivo = this.obtenerCampo(primero);
    if (!objetivo) return;

    objetivo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    objetivo.focus({ preventScroll: true });
  }

  onCampoCambiado(): void {
    const actuales = this.errores();
    if (!actuales) return;

    const nuevos = this.erroresDeCampos();
    const resultado: Record<string, string[]> = {};

    for (const campo of this.ordenCampos) {
      const mensaje = nuevos[campo];
      if (actuales[campo] && mensaje) resultado[campo] = mensaje;
    }

    for (const campo of Object.keys(actuales)) {
      if (!this.ordenCampos.includes(campo)) resultado[campo] = actuales[campo];
    }

    this.errores.set(Object.keys(resultado).length ? resultado : null);

    const banner = this.errorBanner();
    if (banner?.startsWith('Falta completar:')) {
      this.errorBanner.set(Object.keys(resultado).length ? this.resumenErrores(resultado) : null);
    }
  }

  guardar(otra = false): void {
    const f = this.forma;
    const categoriaId = f.categoria_id;
    const tallaId = f.talla_id;

    if (!f.codigo.trim() || !f.nombre.trim() || categoriaId == null || tallaId == null ||
        f.costo == null || f.precio == null || f.costo <= 0 || f.precio <= 0) {
      const errores = this.erroresDeCampos();
      this.errorBanner.set(this.resumenErrores(errores));
      this.errores.set(errores);
      this.enfocarPrimerError(errores);
      return;
    }

    if (f.precio < f.costo) {
      this.errorBanner.set(`El precio (${f.precio}) es menor que el costo (${f.costo}).`);
      this.errores.set(null);
      return;
    }

    const data: ProductoPayload = {
      codigo: f.codigo.trim(),
      nombre: f.nombre.trim(),
      categoria_id: categoriaId,
      talla_id: tallaId,
      marca_id: f.marca_id ?? null,
      color: f.color || null,
      condicion_id: f.condicion_id ?? null,
      medidas: f.medidas || null,
      descripcion: f.descripcion || null,
      costo: f.costo,
      precio: f.precio,
      estado: this.esEdicion ? undefined : 'disponible',
      publicado: this.esEdicion && this.producto()?.estado === 'vendida' ? false : f.publicado,
      fecha_ingreso: f.fecha_ingreso || null,
    };

    const peticion = this.esEdicion
      ? this.productosService.actualizar(this.producto()!.id, data)
      : this.productosService.crear(data);

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    peticion.subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success(this.esEdicion ? 'Producto actualizado.' : 'Producto creado.');

          if (otra && !this.esEdicion) {
            this.trasAgregarOtra(res.data);
          } else if (this.imagenesPendientes().length > 0) {
            this.subirImagenesPendientes(res.data);
          } else {
            this.limpiarPreviews();
            this.guardado.emit(res.data);
          }
        }
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        const detalle = err.errors?.['publicado']?.[0];
        this.errorBanner.set(detalle ?? err.message ?? 'No se pudo guardar el producto.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  private trasAgregarOtra(producto: Producto): void {
    const continuar = () => {
      this.agregado.emit(producto);
      this.reiniciarParaOtra();
    };

    if (this.imagenesPendientes().length > 0) {
      this.imagenesService.subir(producto.id, this.imagenesPendientes().map((img) => img.archivo)).subscribe({
        next: () => continuar(),
        error: () => {
          this.toast.error('La prenda se guardó, pero las fotos no se subieron.');
          continuar();
        },
      });
      return;
    }

    continuar();
  }

  private reiniciarParaOtra(): void {
    const f = this.forma;
    f.codigo = `EV-${Math.floor(1000 + Math.random() * 9000)}`;
    f.nombre = '';
    f.color = '';
    f.medidas = '';
    f.descripcion = '';
    f.costo = null;
    f.precio = null;

    this.limpiarPreviews();
    this.errorBanner.set(null);
    this.errores.set(null);
  }

  private subirImagenesPendientes(producto: Producto): void {
    const archivos = this.imagenesPendientes().map((img) => img.archivo);

    this.imagenesService.subir(producto.id, archivos).subscribe({
      next: (res) => {
        this.limpiarPreviews();
        if (res.success && res.message) {
          this.toast.success(res.message);
        }
        this.guardado.emit(producto);
      },
      error: (err) => {
        this.limpiarPreviews();
        this.toast.error(
          (err.message ?? 'El producto se guardó, pero las fotos no se subieron.') +
            ' Podés agregarlas desde la galería de imágenes.',
        );
        this.guardado.emit(producto);
      },
    });
  }

  onSeleccionarImagenes(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivos = Array.from(input.files ?? []).filter((f) => f.type.startsWith('image/'));

    input.value = '';

    if (archivos.length === 0) {
      this.toast.error('Selecciona archivos de imagen (jpeg, png, webp, gif).');
      return;
    }

    const validos = archivos.filter((f) => f.size <= 4 * 1024 * 1024);

    if (validos.length !== archivos.length) {
      this.toast.error('Algunos archivos superan el tamaño máximo de 4 MB.');
    }

    const pendientes = this.imagenesPendientes();
    for (const archivo of validos) {
      const imagen: ImagenPendiente = { archivo, preview: URL.createObjectURL(archivo), revision: null };
      pendientes.push(imagen);
      this.medirImagen(imagen);
    }
    this.imagenesPendientes.set(pendientes);
  }

  private medirImagen(imagen: ImagenPendiente): void {
    const elemento = new Image();
    elemento.onload = () => {
      imagen.revision = this.calcularRevision(elemento.naturalWidth, elemento.naturalHeight);
      this.imagenesPendientes.set([...this.imagenesPendientes()]);
    };
    elemento.src = imagen.preview;
  }

  private calcularRevision(ancho: number, alto: number): RevisionFoto {
    const relacion = ancho / alto;
    const relacionObjetivo = 4 / 5;
    const recorte = relacion > relacionObjetivo
      ? 1 - relacionObjetivo / relacion
      : 1 - relacion / relacionObjetivo;
    const porcentaje = Math.round(Math.max(0, recorte) * 100);
    const estado: EstadoRevision = porcentaje <= 10 ? 'correcta' : porcentaje <= 30 ? 'ligera' : 'fuerte';
    const etiqueta = estado === 'correcta'
      ? 'Correcta'
      : estado === 'ligera'
        ? 'Se recortará un poco'
        : 'Se cortará bastante';

    return {
      ancho,
      alto,
      recorte: porcentaje,
      bajaResolucion: Math.min(ancho, alto) < 800,
      etiqueta,
      estado,
    };
  }

  quitarImagen(indice: number): void {
    const actual = this.imagenesPendientes();
    const [quitar] = actual.splice(indice, 1);
    URL.revokeObjectURL(quitar.preview);
    this.imagenesPendientes.set(actual);
  }

  private limpiarPreviews(): void {
    for (const img of this.imagenesPendientes()) {
      URL.revokeObjectURL(img.preview);
    }
    this.imagenesPendientes.set([]);
  }

  etiquetaEstado(estado: string): string {
    return ETIQUETA_ESTADO[estado as keyof typeof ETIQUETA_ESTADO] ?? estado;
  }

  etiquetaCondicion(condicion: { nombre: string } | null | undefined): string {
    return condicion?.nombre ?? '—';
  }

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as keyof typeof TONO_ESTADO] ?? 'neutral';
  }

  readonly moneda = formatearMoneda;

  readonly formatearFecha = formatearFechaCompartida;

  cerrar(): void {
    this.limpiarPreviews();
    this.cerrado.emit();
  }
}
