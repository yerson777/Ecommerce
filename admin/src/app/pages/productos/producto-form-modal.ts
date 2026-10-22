import { Component, inject, input, OnInit, output, signal } from '@angular/core';
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

interface ImagenPendiente {
  archivo: File;
  preview: string;
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
      f.publicado = actual.publicado;
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

  guardar(otra = false): void {
    const f = this.forma;
    const categoriaId = f.categoria_id;
    const tallaId = f.talla_id;

    if (!f.codigo.trim() || !f.nombre.trim() || categoriaId == null || tallaId == null || f.costo == null || f.precio == null) {
      this.errorBanner.set('Completa los campos obligatorios.');
      this.errores.set(null);
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
      publicado: f.publicado,
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
        this.errorBanner.set(err.message ?? 'No se pudo guardar el producto.');
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
      pendientes.push({ archivo, preview: URL.createObjectURL(archivo) });
    }
    this.imagenesPendientes.set(pendientes);
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

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }

  cerrar(): void {
    this.limpiarPreviews();
    this.cerrado.emit();
  }
}
