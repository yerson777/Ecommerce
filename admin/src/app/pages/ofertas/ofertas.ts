import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';
import { CategoriaRef, Producto } from '../../core/models/producto';
import {
  ESTADO_ETIQUETAS,
  ESTADOS_OFERTA,
  Oferta,
  OfertaDatos,
  TIPO_ETIQUETAS,
  TIPOS_OFERTA,
  TONO_ESTADO,
} from '../../core/models/oferta';
import { CatalogosService } from '../../core/services/catalogos.service';
import { InventarioService } from '../../core/services/inventario.service';
import { OfertasService } from '../../core/services/ofertas.service';
import { ProductosService } from '../../core/services/productos.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface Formulario {
  nombre: string;
  tipo: 'porcentaje' | 'fijo' | '';
  valor: number | null;
  fecha_inicio: string;
  fecha_fin: string;
  aplicar_a: 'prendas' | 'categorias';
  permitir_cupon: boolean;
  activo: boolean;
}

function formularioVacio(): Formulario {
  return {
    nombre: '',
    tipo: 'porcentaje',
    valor: null,
    fecha_inicio: '',
    fecha_fin: '',
    aplicar_a: 'prendas',
    permitir_cupon: true,
    activo: true,
  };
}

function aLocalInput(iso: string | null | undefined): string {
  if (!iso) {
    return '';
  }
  const fecha = new Date(iso);
  if (Number.isNaN(fecha.getTime())) {
    return '';
  }
  const pad = (n: number): string => String(n).padStart(2, '0');
  return (
    `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}` +
    `T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}`
  );
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
  selector: 'app-ofertas',
  standalone: true,
  styleUrl: './ofertas.scss',
  templateUrl: './ofertas.html',
})
export class OfertasComponent implements OnInit {
  private readonly ofertasService = inject(OfertasService);
  private readonly catalogos = inject(CatalogosService);
  private readonly inventario = inject(InventarioService);
  private readonly productosService = inject(ProductosService);
  private readonly toast = inject(ToastService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  readonly etiquetasTipo = TIPO_ETIQUETAS;
  readonly tipos = TIPOS_OFERTA;
  readonly etiquetasEstado = ESTADO_ETIQUETAS;
  readonly tonosEstado = TONO_ESTADO;
  readonly estados = ESTADOS_OFERTA;

  readonly filtros = signal({ busqueda: '', estado: '' });
  readonly ofertas = signal<Oferta[]>([]);
  readonly cargando = signal(true);

  readonly categorias = signal<CategoriaRef[]>([]);

  readonly modalAbierto = signal(false);
  readonly editando = signal<Oferta | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');
  form: Formulario = formularioVacio();

  readonly productosSeleccionados = signal<Producto[]>([]);
  readonly busquedaPrendas = signal('');
  readonly resultadosBusqueda = signal<Producto[]>([]);
  readonly buscandoPrendas = signal(false);
  categoriasSeleccionadas: number[] = [];

  eliminarObjetivo: Oferta | null = null;
  alternandoId: number | null = null;

  ngOnInit(): void {
    this.cargar();
    this.cargarCategorias();
    this.revisarParametros();
  }

  private revisarParametros(): void {
    const params = this.route.snapshot.queryParamMap;
    if (params.get('crear') !== '1') {
      return;
    }

    const dias = Number(params.get('sin_rotacion') ?? 60) || 60;
    this.abrirCrear();

    this.inventario.sinRotacion(dias, 1, 100).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          const actuales = this.productosSeleccionados();
          const conocidos = new Set(actuales.map((p) => p.id));
          const nuevos = res.data.data.filter((p) => !conocidos.has(p.id));
          this.productosSeleccionados.set([...actuales, ...nuevos]);
          this.errorFormulario.set('');
        }
      },
    });

    this.router.navigate([], { queryParams: {}, replaceUrl: true });
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
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', estado: '' });
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.ofertasService.listar(this.filtros()).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.ofertas.set(res.data);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar las ofertas.');
      },
    });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.form = formularioVacio();
    this.productosSeleccionados.set([]);
    this.categoriasSeleccionadas = [];
    this.resultadosBusqueda.set([]);
    this.busquedaPrendas.set('');
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  abrirEditar(oferta: Oferta): void {
    this.editando.set(oferta);
    this.form = {
      nombre: oferta.nombre,
      tipo: oferta.tipo,
      valor: Number(oferta.valor),
      fecha_inicio: aLocalInput(oferta.fecha_inicio),
      fecha_fin: aLocalInput(oferta.fecha_fin),
      aplicar_a: oferta.aplicar_a,
      permitir_cupon: oferta.permitir_cupon,
      activo: oferta.activo,
    };
    this.productosSeleccionados.set([]);
    this.categoriasSeleccionadas = oferta.categorias.map((c) => c.id);
    this.resultadosBusqueda.set([]);
    this.busquedaPrendas.set('');
    this.errorFormulario.set('');
    this.modalAbierto.set(true);

    if (oferta.productos_ids.length > 0) {
      forkJoin(oferta.productos_ids.map((id) => this.productosService.detalle(id))).subscribe({
        next: (respuestas) => {
          this.productosSeleccionados.set(
            respuestas.filter((r) => r.success && r.data).map((r) => r.data!),
          );
        },
        error: () => {
          this.toast.error('No se pudieron cargar las prendas de la oferta.');
        },
      });
    }
  }

  cerrarModal(): void {
    this.modalAbierto.set(false);
    this.editando.set(null);
  }

  buscarPrendas(): void {
    const termino = this.busquedaPrendas().trim();
    if (!termino) {
      this.resultadosBusqueda.set([]);
      return;
    }

    this.buscandoPrendas.set(true);
    this.inventario
      .listado({ busqueda: termino, estado: 'disponible', per_page: 10 })
      .subscribe({
        next: (res) => {
          this.buscandoPrendas.set(false);
          this.resultadosBusqueda.set(res.success && res.data ? res.data.data : []);
        },
        error: () => {
          this.buscandoPrendas.set(false);
          this.resultadosBusqueda.set([]);
        },
      });
  }

  alternarProducto(producto: Producto): void {
    const actuales = this.productosSeleccionados();
    this.productosSeleccionados.set(
      actuales.some((p) => p.id === producto.id)
        ? actuales.filter((p) => p.id !== producto.id)
        : [...actuales, producto],
    );
  }

  estaSeleccionado(id: number): boolean {
    return this.productosSeleccionados().some((p) => p.id === id);
  }

  quitarProducto(id: number): void {
    this.productosSeleccionados.set(this.productosSeleccionados().filter((p) => p.id !== id));
  }

  alternarCategoria(id: number): void {
    this.categoriasSeleccionadas = this.categoriasSeleccionadas.includes(id)
      ? this.categoriasSeleccionadas.filter((catId) => catId !== id)
      : [...this.categoriasSeleccionadas, id];
  }

  categoriaMarcada(id: number): boolean {
    return this.categoriasSeleccionadas.includes(id);
  }

  nombresCategorias(oferta: Oferta): string {
    const nombres = oferta.categorias?.map((c) => c.nombre) ?? [];
    return nombres.length > 0 ? nombres.join(', ') : '—';
  }

  alanceTexto(oferta: Oferta): string {
    if (oferta.aplicar_a === 'categorias') {
      return this.nombresCategorias(oferta);
    }
    const n = oferta.productos_count;
    return n === 1 ? '1 prenda' : `${n} prendas`;
  }

  valorTexto(oferta: Oferta): string {
    return oferta.tipo === 'porcentaje'
      ? `${Number(oferta.valor)}%`
      : `$ ${Number(oferta.valor)}`;
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    const f = this.form;
    if (!f.nombre.trim()) {
      this.errorFormulario.set('Indicá el nombre de la oferta.');
      return;
    }
    if (!f.tipo || !f.valor || f.valor <= 0) {
      this.errorFormulario.set('Indicá el tipo y un valor mayor a cero.');
      return;
    }
    if (f.tipo === 'porcentaje' && f.valor > 100) {
      this.errorFormulario.set('El porcentaje no puede superar el 100%.');
      return;
    }
    if (!f.fecha_inicio || !f.fecha_fin) {
      this.errorFormulario.set('Indicá las fechas de inicio y fin.');
      return;
    }
    if (new Date(f.fecha_fin) <= new Date(f.fecha_inicio)) {
      this.errorFormulario.set('El fin debe ser posterior al inicio.');
      return;
    }

    const datos: OfertaDatos = {
      nombre: f.nombre.trim(),
      tipo: f.tipo as 'porcentaje' | 'fijo',
      valor: f.valor,
      fecha_inicio: f.fecha_inicio,
      fecha_fin: f.fecha_fin,
      aplicar_a: f.aplicar_a,
      productos:
        f.aplicar_a === 'prendas' ? this.productosSeleccionados().map((p) => p.id) : [],
      categorias: f.aplicar_a === 'categorias' ? this.categoriasSeleccionadas : [],
      permitir_cupon: f.permitir_cupon,
      activo: f.activo,
    };

    if (datos.aplicar_a === 'prendas' && datos.productos.length === 0) {
      this.errorFormulario.set('Seleccioná al menos una prenda.');
      return;
    }
    if (datos.aplicar_a === 'categorias' && datos.categorias.length === 0) {
      this.errorFormulario.set('Seleccioná al menos una categoría.');
      return;
    }

    this.errorFormulario.set('');
    this.guardando.set(true);

    const editando = this.editando();
    const peticion = editando
      ? this.ofertasService.actualizar(editando.id, datos)
      : this.ofertasService.crear(datos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(editando ? 'Oferta actualizada.' : 'Oferta creada.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err) => {
        this.guardando.set(false);
        const errores = err.errors;
        let mensaje = err.message ?? 'No se pudo guardar la oferta.';
        if (errores && typeof errores === 'object') {
          const primera = Object.values(errores).flat()[0];
          if (typeof primera === 'string') {
            mensaje = primera;
          }
        }
        this.errorFormulario.set(mensaje);
      },
    });
  }

  alternarActivo(oferta: Oferta): void {
    if (this.alternandoId !== null) {
      return;
    }

    this.alternandoId = oferta.id;
    this.ofertasService.cambiarEstado(oferta.id, !oferta.activo).subscribe({
      next: () => {
        this.alternandoId = null;
        this.toast.success(oferta.activo ? 'Oferta desactivada.' : 'Oferta activada.');
        this.cargar();
      },
      error: (err) => {
        this.alternandoId = null;
        const errores = err.errors;
        let mensaje = err.message ?? 'No se pudo cambiar el estado de la oferta.';
        if (errores && typeof errores === 'object') {
          const primera = Object.values(errores).flat()[0];
          if (typeof primera === 'string') {
            mensaje = primera;
          }
        }
        this.toast.error(mensaje);
      },
    });
  }

  pedirEliminar(oferta: Oferta): void {
    this.eliminarObjetivo = oferta;
  }

  cancelarEliminar(): void {
    this.eliminarObjetivo = null;
  }

  confirmarEliminar(): void {
    const oferta = this.eliminarObjetivo;
    if (!oferta) {
      return;
    }

    this.eliminarObjetivo = null;
    this.alternandoId = oferta.id;

    this.ofertasService.eliminar(oferta.id).subscribe({
      next: () => {
        this.alternandoId = null;
        this.toast.success('Oferta eliminada.');
        this.cargar();
      },
      error: (err) => {
        this.alternandoId = null;
        this.toast.error(err.message ?? 'No se pudo eliminar la oferta.');
      },
    });
  }
}
