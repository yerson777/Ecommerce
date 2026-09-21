import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Paginated } from '../../core/models/paginated';
import { Rol, ROL_ETIQUETAS, Usuario, UsuarioDatos } from '../../core/models/usuario';
import { AuthService } from '../../core/services/auth.service';
import { ToastService } from '../../core/services/toast.service';
import { UsuariosService } from '../../core/services/usuarios.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface Formulario {
  name: string;
  email: string;
  rol: Rol | '';
  activo: boolean;
  password: string;
}

function formularioVacio(): Formulario {
  return { name: '', email: '', rol: '', activo: true, password: '' };
}

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    ConfirmDialogComponent,
    EmptyStateComponent,
    ModalComponent,
    NotificationCenterComponent,
    PaginatorComponent,
    SpinnerComponent,
  ],
  selector: 'app-usuarios',
  standalone: true,
  styleUrl: './usuarios.scss',
  templateUrl: './usuarios.html',
})
export class UsuariosComponent implements OnInit {
  private readonly usuariosService = inject(UsuariosService);
  private readonly auth = inject(AuthService);
  private readonly toast = inject(ToastService);

  readonly etiquetasRol = ROL_ETIQUETAS;
  readonly roles: Rol[] = ['super_admin', 'admin', 'vendedor'];

  readonly filtros = signal({ busqueda: '', rol: '' });
  readonly resultados = signal<Paginated<Usuario> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly perPage = 15;

  readonly modalAbierto = signal(false);
  readonly editando = signal<Usuario | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');
  form = formularioVacio();

  desactivarObjetivo: Usuario | null = null;
  desactivandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  esYo(usuario: Usuario): boolean {
    return this.auth.usuario()?.id === usuario.id;
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', rol: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  formatearFecha(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-AR');
  }

  cargar(): void {
    this.cargando.set(true);

    this.usuariosService
      .listar({
        busqueda: this.filtros().busqueda,
        rol: (this.filtros().rol || undefined) as Rol | undefined,
        page: this.pagina(),
        per_page: this.perPage,
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
          this.toast.error(err.message ?? 'No se pudieron cargar los usuarios.');
        },
      });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.form = formularioVacio();
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  abrirEditar(usuario: Usuario): void {
    this.editando.set(usuario);
    this.form = {
      name: usuario.name,
      email: usuario.email,
      rol: usuario.role,
      activo: usuario.activo,
      password: '',
    };
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  cerrarModal(): void {
    this.modalAbierto.set(false);
    this.editando.set(null);
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    const editando = this.editando();
    if (!this.form.name.trim() || !this.form.email.trim() || !this.form.rol) {
      this.errorFormulario.set('Completá nombre, correo y rol.');
      return;
    }
    if (!editando && !this.form.password) {
      this.errorFormulario.set('Definí una contraseña inicial para el nuevo usuario.');
      return;
    }
    if (this.form.password && this.form.password.length < 8) {
      this.errorFormulario.set('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    const datos: Partial<UsuarioDatos> = {
      name: this.form.name.trim(),
      email: this.form.email.trim(),
      role: this.form.rol as Rol,
      activo: this.form.activo,
    };

    if (editando) {
      if (this.esYo(editando)) {
        delete datos.role;
        delete datos.activo;
      }
    }

    if (this.form.password) {
      datos.password = this.form.password;
    }

    this.errorFormulario.set('');
    this.guardando.set(true);

    const peticion = editando
      ? this.usuariosService.actualizar(editando.id, datos)
      : this.usuariosService.crear(datos as UsuarioDatos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(editando ? 'Usuario actualizado.' : 'Usuario creado.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err: ApiError) => {
        this.guardando.set(false);
        this.errorFormulario.set(err.message ?? 'No se pudo guardar el usuario.');
      },
    });
  }

  pedirDesactivar(usuario: Usuario): void {
    if (this.esYo(usuario)) {
      return;
    }
    this.desactivarObjetivo = usuario;
  }

  confirmarDesactivar(): void {
    const usuario = this.desactivarObjetivo;
    if (!usuario) {
      return;
    }

    this.desactivarObjetivo = null;
    this.desactivandoId = usuario.id;

    this.usuariosService.actualizar(usuario.id, { role: usuario.role, activo: false }).subscribe({
      next: () => {
        this.desactivandoId = null;
        this.toast.success('Usuario desactivado.');
        this.cargar();
      },
      error: (err) => {
        this.desactivandoId = null;
        this.toast.error(err.message ?? 'No se pudo desactivar el usuario.');
      },
    });
  }

  reactivar(usuario: Usuario): void {
    this.usuariosService.actualizar(usuario.id, { role: usuario.role, activo: true }).subscribe({
      next: () => {
        this.toast.success('Usuario reactivado.');
        this.cargar();
      },
      error: (err) => {
        this.toast.error(err.message ?? 'No se pudo reactivar el usuario.');
      },
    });
  }
}
