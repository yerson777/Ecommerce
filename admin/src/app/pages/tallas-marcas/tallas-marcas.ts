import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Condicion, Marca, Talla } from '../../core/models/catalogo';
import { CatalogosService } from '../../core/services/catalogos.service';
import { ToastService } from '../../core/services/toast.service';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

type Pestana = 'tallas' | 'marcas' | 'condiciones';

interface ItemCatalogo {
  id: number;
  nombre: string;
  slug?: string;
  activo?: boolean;
  orden?: number;
  productos_count?: number;
  logo_url?: string | null;
}

interface Formulario {
  nombre: string;
  slug: string;
  orden: number;
  activo: boolean;
  archivo: File | null;
  preview: string | null;
}

function formularioVacio(): Formulario {
  return { nombre: '', slug: '', orden: 0, activo: true, archivo: null, preview: null };
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
  selector: 'app-tallas-marcas',
  standalone: true,
  styleUrl: './tallas-marcas.scss',
  templateUrl: './tallas-marcas.html',
})
export class TallasMarcasComponent {
  private readonly catalogos = inject(CatalogosService);
  private readonly toast = inject(ToastService);

  readonly pestana = signal<Pestana>('tallas');

  readonly tallas = signal<Talla[]>([]);
  readonly marcas = signal<Marca[]>([]);
  readonly condiciones = signal<Condicion[]>([]);
  readonly cargando = signal(true);

  readonly modalAbierto = signal(false);
  readonly tipoForm = signal<Pestana>('tallas');
  readonly editando = signal<{ id: number; nombre: string; slug?: string; logo_url?: string | null } | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal<string | null>(null);
  readonly errores = signal<Record<string, string[]> | null>(null);

  form: Formulario = formularioVacio();

  eliminarObjetivo: { tipo: Pestana; item: { id: number; nombre: string } } | null = null;

  constructor() {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);

    this.catalogos.tallas().subscribe({
      next: (res) => res.success && res.data && this.tallas.set(res.data),
      error: () => undefined,
    });

    this.catalogos.marcas().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.marcas.set(res.data);
        }
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false),
    });

    this.catalogos.condiciones().subscribe({
      next: (res) => res.success && res.data && this.condiciones.set(res.data),
      error: () => undefined,
    });
  }

  cambiarPestana(pestana: Pestana): void {
    this.pestana.set(pestana);
  }

  // --- Formulario ---

  abrirCrear(tipo: Pestana): void {
    this.tipoForm.set(tipo);
    this.editando.set(null);
    this.form = formularioVacio();
    this.errorFormulario.set(null);
    this.errores.set(null);
    this.modalAbierto.set(true);
  }

  abrirEditar(tipo: Pestana, item: { id: number; nombre: string; slug?: string; orden?: number; activo?: boolean; logo_url?: string | null }): void {
    this.tipoForm.set(tipo);
    this.editando.set({ id: item.id, nombre: item.nombre, slug: item.slug, logo_url: item.logo_url });
    this.form = {
      nombre: item.nombre,
      slug: item.slug ?? '',
      orden: item.orden ?? 0,
      activo: item.activo ?? true,
      archivo: null,
      preview: item.logo_url ?? null,
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

  tituloModal(): string {
    const tipo = this.tipoForm();
    const singular = tipo === 'tallas' ? 'talla' : tipo === 'marcas' ? 'marca' : 'condición';
    return (this.editando() ? 'Editar ' : 'Nueva ') + singular;
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

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    if (!this.form.nombre.trim()) {
      this.errorFormulario.set('El nombre es obligatorio.');
      return;
    }

    const tipo = this.tipoForm();
    const editando = this.editando();
    const base = { nombre: this.form.nombre.trim(), activo: this.form.activo, orden: this.form.orden };

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set(null);

    const peticion =
      tipo === 'tallas'
        ? editando
          ? this.catalogos.actualizarTalla(editando.id, base)
          : this.catalogos.crearTalla(base)
        : tipo === 'condiciones'
          ? editando
            ? this.catalogos.actualizarCondicion(editando.id, base)
            : this.catalogos.crearCondicion(base)
          : editando
            ? this.catalogos.actualizarMarca(editando.id, this.form.archivo, { ...base, slug: this.form.slug || null })
            : this.catalogos.crearMarca(this.form.archivo, { ...base, slug: this.form.slug || null });

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(this.editando() ? 'Actualizado.' : 'Creado.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorFormulario.set(err.message ?? 'No se pudo guardar.');
        this.errores.set(err.errors ?? null);
      },
    });
  }

  errorDe(campo: string): string | undefined {
    return this.errores()?.[campo]?.[0];
  }

  // --- Eliminar ---

  pedirEliminar(tipo: Pestana, item: { id: number; nombre: string }): void {
    this.eliminarObjetivo = { tipo, item };
  }

  confirmarEliminar(): void {
    if (!this.eliminarObjetivo) {
      return;
    }

    const { tipo, item } = this.eliminarObjetivo;
    this.eliminarObjetivo = null;

    const peticion =
      tipo === 'tallas'
        ? this.catalogos.eliminarTalla(item.id)
        : tipo === 'marcas'
          ? this.catalogos.eliminarMarca(item.id)
          : this.catalogos.eliminarCondicion(item.id);

    peticion.subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success('Elemento eliminado.');
          this.cargar();
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudo eliminar.'),
    });
  }

  // --- Activar / ocultar ---

  cambiarActivo(tipo: Pestana, item: { id: number; nombre: string; activo?: boolean; orden?: number; slug?: string }): void {
    const payload = { nombre: item.nombre, activo: !item.activo, orden: item.orden ?? 0 };

    const peticion =
      tipo === 'tallas'
        ? this.catalogos.actualizarTalla(item.id, payload)
        : tipo === 'condiciones'
          ? this.catalogos.actualizarCondicion(item.id, payload)
          : this.catalogos.actualizarMarca(item.id, null, { ...payload, slug: item.slug ?? null });

    peticion.subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success(item.activo ? 'Ocultado del catálogo.' : 'Activado.');
          this.cargar();
        }
      },
      error: (err) => this.toast.error(err.message ?? 'No se pudo cambiar el estado.'),
    });
  }
}
