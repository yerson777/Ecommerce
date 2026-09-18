import { Component, inject, input, OnInit, output } from '@angular/core';
import { Producto, ProductoImagen } from '../../core/models/producto';
import { ImagenesService } from '../../core/services/imagenes.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

@Component({
  imports: [ModalComponent, BadgeComponent, EmptyStateComponent, SpinnerComponent, ConfirmDialogComponent],
  selector: 'app-imagenes-modal',
  standalone: true,
  styleUrl: './imagenes-modal.scss',
  templateUrl: './imagenes-modal.html',
})
export class ImagenesModalComponent implements OnInit {
  readonly producto = input.required<Producto>();

  readonly cerrado = output<void>();
  readonly cambiadas = output<void>();

  private readonly imagenesService = inject(ImagenesService);
  private readonly toast = inject(ToastService);

  readonly imagenes = ([] as ProductoImagen[]).slice();
  cargando = true;
  subiendo = false;
  errorBanner: string | null = null;

  eliminarObjetivo: ProductoImagen | null = null;
  reemplazandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  imagenesOrdenadas(): ProductoImagen[] {
    return [...this.imagenes].sort((a, b) => Number(a.es_principal) - Number(b.es_principal) || a.orden - b.orden);
  }

  cargar(): void {
    this.imagenesService.listar(this.producto().id).subscribe({
      next: (res) => {
        this.cargando = false;
        this.errorBanner = null;
        if (res.success && res.data) {
          (this.imagenes as ProductoImagen[]).length = 0;
          this.imagenes.push(...res.data);
        }
      },
      error: (err) => {
        this.cargando = false;
        this.errorBanner = err.message ?? 'No se pudieron cargar las imágenes.';
      },
    });
  }

  private refrescar(): void {
    this.cargar();
    this.cambiadas.emit();
  }

  onSubirArchivos(event: Event): void {
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

    if (validos.length === 0) {
      return;
    }

    this.subiendo = true;

    this.imagenesService.subir(this.producto().id, validos).subscribe({
      next: (res) => {
        this.subiendo = false;
        if (res.success) {
          this.toast.success(res.message ?? 'Imágenes subidas.');
          this.refrescar();
        }
      },
      error: (err) => {
        this.subiendo = false;
        this.toast.error(err.message ?? 'No se pudieron subir las imágenes.');
      },
    });
  }

  establecerPrincipal(imagen: ProductoImagen): void {
    this.imagenesService.establecerPrincipal(this.producto().id, imagen.id).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success('Imagen principal actualizada.');
          this.refrescar();
        }
      },
      error: (err) => {
        this.toast.error(err.message ?? 'No se pudo actualizar la imagen principal.');
      },
    });
  }

  subir(indice: number): void {
    this.mover(indice, -1);
  }

  bajar(indice: number): void {
    this.mover(indice, 1);
  }

  private mover(indice: number, delta: number): void {
    const actual = this.imagenesOrdenadas();
    const destino = indice + delta;

    if (destino < 0 || destino >= actual.length) {
      return;
    }

    const nuevo = [...actual];
    const [movida] = nuevo.splice(indice, 1);
    nuevo.splice(destino, 0, movida);

    this.imagenesService.reordenar(this.producto().id, nuevo.map((img) => img.id)).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success('Orden actualizado.');
          this.refrescar();
        }
      },
      error: (err) => {
        this.toast.error(err.message ?? 'No se pudo reordenar.');
      },
    });
  }

  onReemplazar(imagen: ProductoImagen, event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    input.value = '';

    if (!archivo || !archivo.type.startsWith('image/')) {
      this.toast.error('Selecciona un archivo de imagen válido.');
      return;
    }

    if (archivo.size > 4 * 1024 * 1024) {
      this.toast.error('El archivo supera el tamaño máximo de 4 MB.');
      return;
    }

    this.reemplazandoId = imagen.id;

    this.imagenesService.reemplazar(this.producto().id, imagen.id, archivo).subscribe({
      next: (res) => {
        this.reemplazandoId = null;
        if (res.success) {
          this.toast.success('Imagen reemplazada.');
          this.refrescar();
        }
      },
      error: (err) => {
        this.reemplazandoId = null;
        this.toast.error(err.message ?? 'No se pudo reemplazar la imagen.');
      },
    });
  }

  confirmarEliminar(): void {
    const objetivo = this.eliminarObjetivo;
    if (!objetivo) {
      return;
    }

    this.imagenesService.eliminar(this.producto().id, objetivo.id).subscribe({
      next: (res) => {
        this.eliminarObjetivo = null;
        if (res.success) {
          this.toast.success('Imagen eliminada.');
          this.refrescar();
        }
      },
      error: (err) => {
        this.eliminarObjetivo = null;
        this.toast.error(err.message ?? 'No se pudo eliminar la imagen.');
      },
    });
  }
}