import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Categoria } from '../../core/models/catalogo';
import { CatalogosService } from '../../core/services/catalogos.service';
import { ToastService } from '../../core/services/toast.service';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface FormCategoria {
  nombre: string;
  slug: string;
  parent_id: number | null;
  orden: number;
  activo: boolean;
  archivo: File | null;
  preview: string | null;
}

function formularioVacio(): FormCategoria {
  return { nombre: '', slug: '', parent_id: null, orden: 0, activo: true, archivo: null, preview: null };
}

@Component({
  imports: [
    FormsModule,
    ConfirmDialogComponent,
    EmptyStateComponent,
    ModalComponent,
    NotificationCenterComponent,
    SpinnerComponent,
  ],
  selector: 'app-categorias',
  standalone: true,
  styleUrl: './categorias.scss',
  templateUrl: './categorias.html',
})
export class CategoriasComponent {
  private readonly catalogos = inject(CatalogosService);
  private readonly toast = inject(ToastService);

  readonly categorias = signal<Categoria[]>([]);
  readonly cargando = signal(true);
  readonly busqueda = signal('');
  readonly filtroActivo = signal<'' | '1' | '0'>('');

  readonly modalAbierto = signal(false);
  readonly editando = signal<Categoria | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  form: FormCategoria = formularioVacio();

  readonly moverObjetivo = signal<Categoria | null>(null);
  destinoMover: number | null = null;

  eliminarObjetivo: Categoria | null = null;

  constructor() {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);

    this.catalogos.categorias().subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.categorias.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar las categorías.');
      },
    });
  }

  filtradas(): Categoria[] {
    const termino = this.busqueda().trim().toLowerCase();
    const activo = this.filtroActivo();

    return this.categorias().filter((categoria) => {
      if (activo === '1' && !categoria.activo) {
        return false;
      }
      if (activo === '0' && categoria.activo) {
        return false;
      }
      if (!termino) {
        return true;
      }
      return categoria.nombre.toLowerCase().includes(termino);
    });
  }

  // --- Crear / editar ---

  abrirCrear(): void {
    this.editando.set(null);
    this.form = formularioVacio();
    this.errorFormulario.set(null);
    this.errores.set(null);
    this.modalAbierto.set(true);
  }

  abrirEditar(categoria: Categoria): void {
    this.editando.set(categoria);
    this.form = {
      nombre: categoria.nombre,
      slug: categoria.slug ?? '',
      parent_id: categoria.parent_id ?? null,
      orden: categoria.orden ?? 0,
      activo: categoria.activo ?? true,
      archivo: null,
      preview: categoria.imagen_url ?? null,
    };
    this.errorFormulario.set(null);
    this.errores.set(null);
    this.modalAbierto.set(true);
  }

  cerrarModal(): void {
    this.limpiarPreview();
    this.modalAbierto.set(false);
    this.editando.set(null);
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;
    input.value = '';

    if (!archivo) {
      return;
    }

    if (!archivo.type.startsWith('image/')) {
      this.toast.error('Selecciona una imagen (jpeg, png, webp, gif).');
      return;
    }

    if (archivo.size > 4 * 1024 * 1024) {
      this.toast.error('La imagen supera el tamaño máximo de 4 MB.');
      return;
    }

    this.limpiarPreview();
    this.form.archivo = archivo;
    this.form.preview = URL.createObjectURL(archivo);
  }

  quitarImagen(): void {
    this.limpiarPreview();
    this.form.archivo = null;
    this.form.preview = null;
  }

  private limpiarPreview(): void {
    if (this.form.preview && this.form.preview.startsWith('blob:')) {
      URL.revokeObjectURL(this.form.preview);
    }
  }

  opcionesParent(): Categoria[] {
    const actual = this.editando();
    return this.categorias().filter((categoria) => categoria.id !== actual?.id);
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    if (!this.form.nombre.trim()) {
      this.errorFormulario.set('El nombre es obligatorio.');
      return;
    }

    const datos = {
      nombre: this.form.nombre.trim(),
      slug: this.form.slug.trim() || null,
      parent_id: this.form.parent_id ?? null,
      activo: this.form.activo,
      orden: Number(this.form.orden) || 0,
    };

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set(null);

    const editando = this.editando();
    const peticion = editando
      ? this.catalogos.actualizarCategoria(editando.id, this.form.archivo, datos)
      : this.catalogos.crearCategoria(this.form.archivo, datos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(this.editando() ? 'Categoría actualizada.' : 'Categoría creada.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorFormulario.set(err.message ?? 'No se pudo guardar la categoría.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  cambiarActivo(categoria: Categoria): void {
    this.catalogos
      .actualizarCategoria(categoria.id, null, {
        nombre: categoria.nombre,
        slug: categoria.slug ?? null,
        parent_id: categoria.parent_id ?? null,
        activo: !categoria.activo,
        orden: categoria.orden ?? 0,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.toast.success(categoria.activo ? 'Categoría ocultada.' : 'Categoría activada.');
            this.cargar();
          }
        },
        error: (err) => this.toast.error(err.message ?? 'No se pudo cambiar el estado.'),
      });
  }

  // --- Mover prendas ---

  abrirMover(categoria: Categoria): void {
    this.destinoMover = null;
    this.moverObjetivo.set(categoria);
  }

  cerrarMover(): void {
    this.moverObjetivo.set(null);
    this.destinoMover = null;
  }

  opcionesDestino(): Categoria[] {
    const origen = this.moverObjetivo();
    return this.categorias().filter((categoria) => categoria.id !== origen?.id);
  }

  opcionesPadre(): Categoria[] {
    const actual = this.editando();
    return this.categorias().filter((categoria) => categoria.id !== actual?.id);
  }

  confirmarMover(): void {
    const origen = this.moverObjetivo();
    if (!origen || this.destinoMover == null) {
      return;
    }

    this.catalogos.moverProductosCategoria(origen.id, this.destinoMover).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(res.message ?? 'Prendas reasignadas.');
          this.cerrarMover();
          this.cargar();
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudieron reasignar las prendas.'),
    });
  }

  // --- Eliminar ---

  pedirEliminar(categoria: Categoria): void {
    this.eliminarObjetivo = categoria;
  }

  confirmarEliminar(): void {
    const categoria = this.eliminarObjetivo;
    if (!categoria) {
      return;
    }

    this.catalogos.eliminarCategoria(categoria.id).subscribe({
      next: (res) => {
        this.eliminarObjetivo = null;
        if (res.success) {
          this.toast.success('Categoría eliminada.');
          this.cargar();
        }
      },
      error: (err) => {
        this.eliminarObjetivo = null;
        this.toast.error(err.message ?? 'No se pudo eliminar la categoría.');
      },
    });
  }

  nombrePadre(categoria: Categoria): string {
    return categoria.parent?.nombre ?? '—';
  }
}
