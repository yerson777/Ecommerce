import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  Proveedor,
  ProveedorDatos,
  ProveedorFiltros,
  TIPO_PROVEEDOR_ETIQUETAS,
  TIPOS_PROVEEDOR,
  TipoProveedor,
} from '../../core/models/proveedor';
import { ProveedoresService } from '../../core/services/proveedores.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface Formulario {
  nombre: string;
  contacto: string;
  telefono: string;
  ciudad: string;
  tipo: TipoProveedor;
  nota: string;
  activo: boolean;
}

function formularioVacio(): Formulario {
  return {
    nombre: '',
    contacto: '',
    telefono: '',
    ciudad: '',
    tipo: 'otro',
    nota: '',
    activo: true,
  };
}

interface FiltrosVista {
  busqueda: string;
  tipo: TipoProveedor | '';
  activo: '' | 'activos' | 'inactivos';
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
  selector: 'app-proveedores',
  standalone: true,
  styleUrl: './proveedores.scss',
  templateUrl: './proveedores.html',
})
export class ProveedoresComponent implements OnInit {
  private readonly proveedoresService = inject(ProveedoresService);
  private readonly toast = inject(ToastService);

  readonly etiquetasTipo = TIPO_PROVEEDOR_ETIQUETAS;
  readonly tipos = TIPOS_PROVEEDOR;

  readonly filtros = signal<FiltrosVista>({ busqueda: '', tipo: '', activo: '' });

  readonly proveedores = signal<Proveedor[]>([]);
  readonly cargando = signal(true);

  readonly modalAbierto = signal(false);
  readonly editando = signal<Proveedor | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');
  form: Formulario = formularioVacio();

  readonly detalle = signal<Proveedor | null>(null);
  readonly detalleAbierto = signal(false);
  readonly cargandoDetalle = signal(false);

  eliminarObjetivo: Proveedor | null = null;
  alternandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  onBuscar(): void {
    this.cargar();
  }

  private filtrosServicio(): ProveedorFiltros {
    const f = this.filtros();
    return {
      busqueda: f.busqueda || undefined,
      tipo: f.tipo || undefined,
      activo: f.activo === 'activos' ? true : f.activo === 'inactivos' ? false : null,
    };
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', tipo: '', activo: '' });
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.proveedoresService.listar(this.filtrosServicio()).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.proveedores.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar los proveedores.');
      },
    });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.form = formularioVacio();
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  abrirEditar(proveedor: Proveedor): void {
    this.editando.set(proveedor);
    this.form = {
      nombre: proveedor.nombre,
      contacto: proveedor.contacto ?? '',
      telefono: proveedor.telefono ?? '',
      ciudad: proveedor.ciudad ?? '',
      tipo: proveedor.tipo,
      nota: proveedor.nota ?? '',
      activo: proveedor.activo,
    };
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  cerrarModal(): void {
    this.modalAbierto.set(false);
    this.editando.set(null);
  }

  verDetalle(proveedor: Proveedor): void {
    this.detalle.set(proveedor);
    this.detalleAbierto.set(true);
    this.cargandoDetalle.set(true);

    this.proveedoresService.detalle(proveedor.id).subscribe({
      next: (res) => {
        this.cargandoDetalle.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
        }
      },
      error: (err) => {
        this.cargandoDetalle.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar el proveedor.');
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    const f = this.form;
    if (!f.nombre.trim()) {
      this.errorFormulario.set('Indicá el nombre del proveedor.');
      return;
    }

    const datos: ProveedorDatos = {
      nombre: f.nombre.trim(),
      contacto: f.contacto.trim() || null,
      telefono: f.telefono.trim() || null,
      ciudad: f.ciudad.trim() || null,
      tipo: f.tipo,
      nota: f.nota.trim() || null,
      activo: f.activo,
    };

    this.errorFormulario.set('');
    this.guardando.set(true);

    const editando = this.editando();
    const peticion = editando
      ? this.proveedoresService.actualizar(editando.id, datos)
      : this.proveedoresService.crear(datos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(editando ? 'Proveedor actualizado.' : 'Proveedor creado.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err) => {
        this.guardando.set(false);
        this.errorFormulario.set(this.extraerMensaje(err, 'No se pudo guardar el proveedor.'));
      },
    });
  }

  alternarActivo(proveedor: Proveedor): void {
    if (this.alternandoId !== null) {
      return;
    }

    this.alternandoId = proveedor.id;
    this.proveedoresService.cambiarEstado(proveedor.id, !proveedor.activo).subscribe({
      next: () => {
        this.alternandoId = null;
        this.toast.success(proveedor.activo ? 'Proveedor desactivado.' : 'Proveedor activado.');
        this.cargar();
      },
      error: (err) => {
        this.alternandoId = null;
        this.toast.error(this.extraerMensaje(err, 'No se pudo cambiar el estado.'));
      },
    });
  }

  pedirEliminar(proveedor: Proveedor): void {
    this.eliminarObjetivo = proveedor;
  }

  cancelarEliminar(): void {
    this.eliminarObjetivo = null;
  }

  confirmarEliminar(): void {
    const proveedor = this.eliminarObjetivo;
    if (!proveedor) {
      return;
    }

    this.eliminarObjetivo = null;
    this.alternandoId = proveedor.id;

    this.proveedoresService.eliminar(proveedor.id).subscribe({
      next: () => {
        this.alternandoId = null;
        this.toast.success('Proveedor eliminado.');
        this.cargar();
      },
      error: (err) => {
        this.alternandoId = null;
        this.toast.error(this.extraerMensaje(err, 'No se pudo eliminar el proveedor.'));
      },
    });
  }

  tipoTexto(proveedor: Proveedor): string {
    return this.etiquetasTipo[proveedor.tipo] ?? proveedor.tipo;
  }

  private extraerMensaje(err: { message?: string; errors?: Record<string, string[]> | null }, porDefecto: string): string {
    const errores = err.errors;
    if (errores && typeof errores === 'object') {
      const primera = Object.values(errores).flat()[0];
      if (typeof primera === 'string') {
        return primera;
      }
    }
    return err.message ?? porDefecto;
  }
}
