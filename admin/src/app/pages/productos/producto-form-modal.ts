import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import {
  CategoriaRef,
  ETIQUETA_ESTADO,
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

export interface FormaProducto {
  codigo: string;
  nombre: string;
  categoria_id: number | null;
  talla_id: number | null;
  color: string;
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
  readonly soloLectura = input(false);

  readonly guardado = output<Producto>();
  readonly cerrado = output<void>();

  private readonly productosService = inject(ProductosService);
  private readonly imagenesService = inject(ImagenesService);
  private readonly toast = inject(ToastService);

  readonly forma: FormaProducto = {
    codigo: '',
    nombre: '',
    categoria_id: null,
    talla_id: null,
    color: '',
    descripcion: '',
    costo: null,
    precio: null,
    publicado: true,
    fecha_ingreso: '',
  };

  esEdicion = false;
  readonly = false;
  readonly guardando = signal(false);
  readonly errorBanner = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  readonly imagenesPendientes = signal<ImagenPendiente[]>([]);

  ngOnInit(): void {
    const actual = this.producto();
    this.esEdicion = actual != null;
    this.readonly = this.soloLectura();

    if (actual) {
      this.forma.codigo = actual.codigo;
      this.forma.nombre = actual.nombre;
      this.forma.categoria_id = actual.categoria?.id ?? null;
      this.forma.talla_id = actual.talla?.id ?? null;
      this.forma.color = actual.color ?? '';
      this.forma.descripcion = actual.descripcion ?? '';
      this.forma.costo = parseFloat(actual.costo);
      this.forma.precio = parseFloat(actual.precio);
      this.forma.publicado = actual.publicado;
      this.forma.fecha_ingreso = actual.fecha_ingreso ?? '';
    } else {
      this.forma.codigo = `EV-${Math.floor(1000 + Math.random() * 9000)}`;
      this.forma.fecha_ingreso = new Date().toISOString().slice(0, 10);
    }
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  guardar(): void {
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
      color: f.color.trim() || null,
      descripcion: f.descripcion.trim() || null,
      costo: f.costo,
      precio: f.precio,
      publicado: f.publicado,
      fecha_ingreso: f.fecha_ingreso || null,
    };

    if (!this.esEdicion) {
      data.estado = 'disponible';
    }

    this.guardando.set(true);
    this.errorBanner.set(null);
    this.errores.set(null);

    const peticion = this.esEdicion
      ? this.productosService.actualizar(this.producto()!.id, data)
      : this.productosService.crear(data);

    peticion.subscribe({
      next: (res) => {
        this.guardando.set(false);
        if (res.success && res.data) {
          this.toast.success(this.esEdicion ? 'Producto actualizado.' : 'Producto creado.');
          if (this.imagenesPendientes().length > 0) {
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

  tonoEstado(estado: string): string {
    return TONO_ESTADO[estado as keyof typeof TONO_ESTADO] ?? 'neutral';
  }

  moneda(valor: string | number): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(valor);
    return `$${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
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