import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Banner } from '../../core/models/banner';
import { BannersService } from '../../core/services/banners.service';
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
  enlace: string;
  activo: boolean;
  archivo: File | null;
}

function formularioVacio(): Formulario {
  return { titulo: '', subtitulo: '', enlace: '', activo: true, archivo: null };
}

@Component({
  imports: [
    FormsModule,
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
  private readonly toast = inject(ToastService);

  readonly banners = signal<Banner[]>([]);
  readonly cargando = signal(true);

  readonly modalAbierto = signal(false);
  readonly editando = signal<Banner | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');

  form = formularioVacio();

  eliminarObjetivo: Banner | null = null;
  reordenandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  bannersOrdenados(): Banner[] {
    return [...this.banners()].sort((a, b) => a.orden - b.orden);
  }

  cargar(): void {
    this.cargando.set(true);

    this.bannersService.listar().subscribe({
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
  }

  cerrarModal(): void {
    this.modalAbierto.set(false);
    this.editando.set(null);
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.form.archivo = input.files?.[0] ?? null;
    input.value = '';
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    if (!this.editando() && !this.form.archivo) {
      this.errorFormulario.set('Debe adjuntar una imagen para crear el banner.');
      return;
    }

    const datos = {
      titulo: this.form.titulo || null,
      subtitulo: this.form.subtitulo || null,
      enlace: this.form.enlace || null,
      activo: this.form.activo,
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
        this.errorFormulario.set(err.message ?? 'No se pudo guardar el banner.');
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

  reordenar(ordenes: number[]): void {
    this.reordenandoId = null;

    this.bannersService.reordenar(ordenes).subscribe({
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

  private cargarFormularioBanner(banner: Banner | null): void {
    this.form = {
      titulo: banner?.titulo ?? '',
      subtitulo: banner?.subtitulo ?? '',
      enlace: banner?.enlace ?? '',
      activo: banner?.activo ?? true,
      archivo: null,
    };
    this.errorFormulario.set('');
  }
}