import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiError } from '../../core/models/api-response';
import { Paginated } from '../../core/models/paginated';
import {
  Cupon,
  CuponDatos,
  ESTADO_ETIQUETAS,
  TIPOS_CUPON,
  TIPO_ETIQUETAS,
  TONO_ESTADO,
  TONO_TIPO,
} from '../../core/models/cupon';
import { CuponesService } from '../../core/services/cupones.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface Formulario {
  codigo: string;
  tipo: 'porcentaje' | 'fijo' | '';
  valor: number | null;
  minimo_compra: number | null;
  limite_usos: number | null;
  vence_en: string;
  activo: boolean;
}

function formularioVacio(): Formulario {
  return {
    codigo: '',
    tipo: '',
    valor: null,
    minimo_compra: null,
    limite_usos: null,
    vence_en: '',
    activo: true,
  };
}

function alFormulario(cupon: Cupon): Formulario {
  return {
    codigo: cupon.codigo,
    tipo: cupon.tipo as 'porcentaje' | 'fijo',
    valor: cupon.valor !== null ? Number(cupon.valor) : null,
    minimo_compra: cupon.minimo_compra !== null ? Number(cupon.minimo_compra) : null,
    limite_usos: cupon.limite_usos ?? null,
    vence_en: cupon.vence_en ?? '',
    activo: cupon.activo,
  };
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
    PaginatorComponent,
    SpinnerComponent,
  ],
  selector: 'app-cupones',
  standalone: true,
  styleUrl: './cupones.scss',
  templateUrl: './cupones.html',
})
export class CuponesComponent implements OnInit {
  private readonly cuponesService = inject(CuponesService);
  private readonly toast = inject(ToastService);

  readonly etiquetasTipo = TIPO_ETIQUETAS;
  readonly tipos = TIPOS_CUPON;
  readonly etiquetasEstado = ESTADO_ETIQUETAS;
  readonly tonosEstado = TONO_ESTADO;
  readonly tonosTipo = TONO_TIPO;

  readonly filtros = signal({ busqueda: '', tipo: '' });
  readonly resultados = signal<Paginated<Cupon> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly perPage = 15;

  readonly modalAbierto = signal(false);
  readonly editando = signal<Cupon | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');
  form = formularioVacio();

  eliminarObjetivo: Cupon | null = null;
  desactivandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', tipo: '' });
    this.onBuscar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    const filtros = this.filtros();
    this.cuponesService
      .listar({ ...filtros, page: this.pagina(), per_page: this.perPage })
      .subscribe({
        next: (res) => {
          this.cargando.set(false);
          if (res.success && res.data) {
            this.resultados.set(res.data);
          }
        },
        error: (err) => {
          this.cargando.set(false);
          this.toast.error(err.message ?? 'No se pudieron cargar los cupones.');
        },
      });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.form = formularioVacio();
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  abrirEditar(cupon: Cupon): void {
    this.editando.set(cupon);
    this.form = alFormulario(cupon);
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  cerrarModal(): void {
    this.modalAbierto.set(false);
    this.editando.set(null);
  }

  guardar(): void {
    if (this.guardando() || !this.form.tipo) {
      return;
    }

    const editando = this.editando();
    const datos: CuponDatos = {
      codigo: this.form.codigo.trim(),
      tipo: this.form.tipo as 'porcentaje' | 'fijo',
      valor: this.form.valor ?? 0,
      minimo_compra: this.form.minimo_compra,
      limite_usos: this.form.limite_usos,
      vence_en: this.form.vence_en || null,
      activo: this.form.activo,
    };

    if (!datos.codigo || datos.valor <= 0) {
      this.errorFormulario.set('Completá el código y el valor del cupón.');
      return;
    }
    if (datos.tipo === 'porcentaje' && datos.valor > 100) {
      this.errorFormulario.set('El porcentaje no puede superar el 100%.');
      return;
    }

    this.errorFormulario.set('');
    this.guardando.set(true);

    const peticion = editando
      ? this.cuponesService.actualizar(editando.id, datos)
      : this.cuponesService.crear(datos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(editando ? 'Cupón actualizado.' : 'Cupón creado.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err) => {
        this.guardando.set(false);
        this.errorFormulario.set(
          err.errors?.codigo?.[0] ?? err.message ?? 'No se pudo guardar el cupón.',
        );
      },
    });
  }

  pedirEliminar(cupon: Cupon): void {
    this.eliminarObjetivo = cupon;
  }

  confirmarEliminar(): void {
    const cupon = this.eliminarObjetivo;
    if (!cupon) {
      return;
    }

    this.eliminarObjetivo = null;
    this.desactivandoId = cupon.id;

    this.cuponesService.eliminar(cupon.id).subscribe({
      next: () => {
        this.desactivandoId = null;
        this.toast.success('Cupón eliminado.');
        this.cargar();
      },
      error: (err) => {
        this.desactivandoId = null;
        this.toast.error(err.message ?? 'No se pudo eliminar el cupón.');
      },
    });
  }
}
