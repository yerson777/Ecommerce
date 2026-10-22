import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Paginated } from '../../core/models/paginated';
import {
  Cupon,
  CuponDatos,
  CuponUso,
  ESTADO_ETIQUETAS,
  TIPOS_CUPON,
  TIPO_ETIQUETAS,
  TONO_ESTADO,
  TONO_TIPO,
} from '../../core/models/cupon';
import { CategoriaRef } from '../../core/models/producto';
import { CatalogosService } from '../../core/services/catalogos.service';
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
  limite_por_cliente: number | null;
  fecha_inicio: string;
  vence_en: string;
  activo: boolean;
  categorias: number[];
}

function formularioVacio(): Formulario {
  return {
    codigo: '',
    tipo: '',
    valor: null,
    minimo_compra: null,
    limite_usos: null,
    limite_por_cliente: null,
    fecha_inicio: '',
    vence_en: '',
    activo: true,
    categorias: [],
  };
}

function alFormulario(cupon: Cupon): Formulario {
  return {
    codigo: cupon.codigo,
    tipo: cupon.tipo as 'porcentaje' | 'fijo',
    valor: cupon.valor !== null ? Number(cupon.valor) : null,
    minimo_compra: cupon.minimo_compra !== null ? Number(cupon.minimo_compra) : null,
    limite_usos: cupon.limite_usos ?? null,
    limite_por_cliente: cupon.limite_por_cliente ?? null,
    fecha_inicio: cupon.fecha_inicio ?? '',
    vence_en: cupon.vence_en ?? '',
    activo: cupon.activo,
    categorias: cupon.categorias_ids ?? cupon.categorias?.map((c) => c.id) ?? [],
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
  private readonly catalogos = inject(CatalogosService);
  private readonly toast = inject(ToastService);

  readonly etiquetasTipo = TIPO_ETIQUETAS;
  readonly tipos = TIPOS_CUPON;
  readonly etiquetasEstado = ESTADO_ETIQUETAS;
  readonly tonosEstado = TONO_ESTADO;
  readonly tonosTipo = TONO_TIPO;

  readonly filtros = signal({ busqueda: '', tipo: '', estado: '' });
  readonly resultados = signal<Paginated<Cupon> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly perPage = 15;

  readonly categorias = signal<CategoriaRef[]>([]);

  readonly modalAbierto = signal(false);
  readonly editando = signal<Cupon | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');
  form = formularioVacio();

  readonly detalleAbierto = signal(false);
  readonly detalle = signal<Cupon | null>(null);
  readonly cargandoDetalle = signal(false);

  eliminarObjetivo: Cupon | null = null;
  desactivandoId: number | null = null;
  alternandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
    this.cargarCategorias();
  }

  private cargarCategorias(): void {
    this.catalogos.categorias().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.categorias.set(res.data);
        }
      },
    });
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', tipo: '', estado: '' });
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

  toggleCategoria(id: number): void {
    const actuales = this.form.categorias;
    this.form.categorias = actuales.includes(id)
      ? actuales.filter((catId) => catId !== id)
      : [...actuales, id];
  }

  estaSeleccionada(id: number): boolean {
    return this.form.categorias.includes(id);
  }

  guardar(): void {
    if (this.guardando() || !this.form.tipo) {
      return;
    }

    const editando = this.editando();
    const datos: CuponDatos = {
      codigo: this.form.codigo.trim().toUpperCase(),
      tipo: this.form.tipo as 'porcentaje' | 'fijo',
      valor: this.form.valor ?? 0,
      minimo_compra: this.form.minimo_compra,
      limite_usos: this.form.limite_usos,
      limite_por_cliente: this.form.limite_por_cliente,
      fecha_inicio: this.form.fecha_inicio || null,
      vence_en: this.form.vence_en || null,
      activo: this.form.activo,
      categorias: this.form.categorias,
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

  alternarActivo(cupon: Cupon): void {
    if (this.alternandoId !== null) {
      return;
    }

    this.alternandoId = cupon.id;
    this.cuponesService.actualizar(cupon.id, { activo: !cupon.activo }).subscribe({
      next: () => {
        this.alternandoId = null;
        this.toast.success(cupon.activo ? 'Cupón desactivado.' : 'Cupón activado.');
        this.cargar();
      },
      error: (err) => {
        this.alternandoId = null;
        this.toast.error(err.message ?? 'No se pudo cambiar el estado del cupón.');
      },
    });
  }

  abrirDetalle(cupon: Cupon): void {
    this.detalle.set(null);
    this.detalleAbierto.set(true);
    this.cargandoDetalle.set(true);

    this.cuponesService.detalle(cupon.id).subscribe({
      next: (res) => {
        this.cargandoDetalle.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
        }
      },
      error: (err) => {
        this.cargandoDetalle.set(false);
        this.detalleAbierto.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar el detalle del cupón.');
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  nombresCategorias(cupon: Cupon): string {
    const nombres = cupon.categorias?.map((c) => c.nombre) ?? [];
    return nombres.length > 0 ? nombres.join(', ') : 'Todas';
  }

  usosDe(cupon: Cupon): CuponUso[] {
    return cupon.usos_registrados ?? [];
  }

  pedirEliminar(cupon: Cupon): void {
    this.eliminarObjetivo = cupon;
  }

  cancelarEliminar(): void {
    this.eliminarObjetivo = null;
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
