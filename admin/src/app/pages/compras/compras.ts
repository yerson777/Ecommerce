import { Component, inject, OnInit, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import {
  Compra,
  CompraDatos,
  CompraFiltros,
  CompraPrendaItem,
  CompraProductoDetalle,
  ESTADO_COMPRA_ETIQUETAS,
  TONO_ESTADO_COMPRA,
} from '../../core/models/compra';
import { Producto } from '../../core/models/producto';
import { Proveedor, TIPO_PROVEEDOR_ETIQUETAS } from '../../core/models/proveedor';
import { ComprasService } from '../../core/services/compras.service';
import { InventarioService } from '../../core/services/inventario.service';
import { ProductosService } from '../../core/services/productos.service';
import { ProveedoresService } from '../../core/services/proveedores.service';
import { ToastService } from '../../core/services/toast.service';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { ModalComponent } from '../../shared/components/modal/modal';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';

interface Formulario {
  proveedor_id: number | null;
  fecha: string;
  costo_total: number | null;
  gastos_extra: number | null;
  prendas_total: number | null;
  prendas_descartadas: number | null;
  nota: string;
}

function formularioVacio(): Formulario {
  const hoy = new Date();
  const pad = (n: number): string => String(n).padStart(2, '0');
  return {
    proveedor_id: null,
    fecha: `${hoy.getFullYear()}-${pad(hoy.getMonth() + 1)}-${pad(hoy.getDate())}`,
    costo_total: null,
    gastos_extra: 0,
    prendas_total: null,
    prendas_descartadas: 0,
    nota: '',
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
  selector: 'app-compras',
  standalone: true,
  styleUrl: './compras.scss',
  templateUrl: './compras.html',
})
export class ComprasComponent implements OnInit {
  private readonly comprasService = inject(ComprasService);
  private readonly proveedoresService = inject(ProveedoresService);
  private readonly inventario = inject(InventarioService);
  private readonly productosService = inject(ProductosService);
  private readonly toast = inject(ToastService);

  readonly etiquetasEstado = ESTADO_COMPRA_ETIQUETAS;
  readonly tonosEstado = TONO_ESTADO_COMPRA;
  readonly etiquetasTipoProveedor = TIPO_PROVEEDOR_ETIQUETAS;

  readonly filtros = signal<CompraFiltros>({
    fecha_desde: '',
    fecha_hasta: '',
    proveedor_id: null,
    estado: '',
    page: 1,
    per_page: 15,
  });

  readonly compras = signal<Compra[]>([]);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly ultimaPagina = signal(1);
  readonly total = signal(0);
  readonly porPagina = signal(15);

  readonly proveedores = signal<Proveedor[]>([]);

  readonly modalAbierto = signal(false);
  readonly editando = signal<Compra | null>(null);
  readonly guardando = signal(false);
  readonly errorFormulario = signal('');
  form: Formulario = formularioVacio();

  readonly prendasSeleccionadas = signal<Producto[]>([]);
  readonly busquedaPrendas = signal('');
  readonly resultadosBusqueda = signal<Producto[]>([]);
  readonly buscandoPrendas = signal(false);
  readonly costosManuales = signal<Record<number, number | null>>({});

  readonly detalle = signal<Compra | null>(null);
  readonly detalleAbierto = signal(false);
  readonly cargandoDetalle = signal(false);

  eliminarObjetivo: Compra | null = null;
  anularObjetivo: Compra | null = null;
  procesandoId: number | null = null;

  ngOnInit(): void {
    this.cargarProveedores();
    this.cargar();
  }

  private cargarProveedores(): void {
    this.proveedoresService.listar({ activo: true }).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.proveedores.set(res.data);
        }
      },
    });
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ fecha_desde: '', fecha_hasta: '', proveedor_id: null, estado: '', page: 1, per_page: 15 });
    this.pagina.set(1);
    this.cargar();
  }

  irAPagina(pagina: number): void {
    this.pagina.set(pagina);
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    const f = this.filtros();
    const filtros: CompraFiltros = { ...f, page: this.pagina(), per_page: this.porPagina() };

    this.comprasService.listar(filtros).subscribe({
      next: (res) => {
        this.cargando.set(false);
        if (res.success && res.data) {
          this.compras.set(res.data.data);
          this.pagina.set(res.data.current_page);
          this.ultimaPagina.set(res.data.last_page);
          this.total.set(res.data.total);
        }
      },
      error: (err) => {
        this.cargando.set(false);
        this.toast.error(err.message ?? 'No se pudieron cargar las compras.');
      },
    });
  }

  exportar(): void {
    const f = this.filtros();
    this.comprasService.exportar(f).subscribe({
      next: (blob) => this.descargarBlob(blob, 'everly_compras.csv'),
      error: (err) => this.toast.error(err.message ?? 'No se pudo exportar las compras.'),
    });
  }

  private descargarBlob(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombre;
    enlace.click();
    URL.revokeObjectURL(url);
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.form = formularioVacio();
    this.prendasSeleccionadas.set([]);
    this.costosManuales.set({});
    this.resultadosBusqueda.set([]);
    this.busquedaPrendas.set('');
    this.errorFormulario.set('');
    this.modalAbierto.set(true);
  }

  abrirEditar(compra: Compra): void {
    this.editando.set(compra);
    this.form = {
      proveedor_id: compra.proveedor_id,
      fecha: compra.fecha,
      costo_total: Number(compra.costo_total),
      gastos_extra: Number(compra.gastos_extra),
      prendas_total: compra.prendas_total > 0 ? compra.prendas_total : null,
      prendas_descartadas: compra.prendas_descartadas ?? 0,
      nota: compra.nota ?? '',
    };
    this.prendasSeleccionadas.set([]);
    this.costosManuales.set({});
    this.resultadosBusqueda.set([]);
    this.busquedaPrendas.set('');
    this.errorFormulario.set('');
    this.modalAbierto.set(true);

    this.comprasService.detalle(compra.id).subscribe({
      next: (res) => {
        if (res.success && res.data) {
          const items = res.data.productos ?? res.data.detalle_precios ?? [];
          const ids = items.map((i) => i.producto_id);
          const manuales: Record<number, number | null> = {};
          for (const item of items) {
            manuales[item.producto_id] = Number(item.costo_asignado);
          }
          this.costosManuales.set(manuales);

          if (ids.length > 0) {
            forkJoin(ids.map((id) => this.productosService.detalle(id))).subscribe({
              next: (respuestas) => {
                this.prendasSeleccionadas.set(
                  respuestas.filter((r) => r.success && r.data).map((r) => r.data!),
                );
              },
            });
          }
        }
      },
      error: () => this.toast.error('No se pudieron cargar las prendas de la compra.'),
    });
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
    this.inventario.listado({ busqueda: termino, per_page: 10 }).subscribe({
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
    const actuales = this.prendasSeleccionadas();
    if (actuales.some((p) => p.id === producto.id)) {
      this.prendasSeleccionadas.set(actuales.filter((p) => p.id !== producto.id));
      this.costosManuales.update((m) => {
        const copia = { ...m };
        delete copia[producto.id];
        return copia;
      });
    } else {
      this.prendasSeleccionadas.set([...actuales, producto]);
      this.repartirIgual();
    }
  }

  estaSeleccionado(id: number): boolean {
    return this.prendasSeleccionadas().some((p) => p.id === id);
  }

  quitarProducto(id: number): void {
    this.prendasSeleccionadas.set(this.prendasSeleccionadas().filter((p) => p.id !== id));
    this.costosManuales.update((m) => {
      const copia = { ...m };
      delete copia[id];
      return copia;
    });
    this.repartirIgual();
  }

  /**
   * Reparte el costo repartible (costo_total + gastos_extra) en partes iguales
   * entre las prendas vendibles (total del fardo menos descartadas) y refresca
   * el preview. No pisa valores manuales ya cargados.
   */
  repartirIgual(): void {
    const total = this.costoRepartible();
    const prendas = this.prendasSeleccionadas();
    if (prendas.length === 0) {
      return;
    }
    const divisor = this.prendasVendibles();
    const porPrenda = divisor > 0 ? Math.round((total / divisor) * 100) / 100 : 0;
    const nuevo: Record<number, number | null> = {};
    for (const p of prendas) {
      const actual = this.costosManuales()[p.id];
      nuevo[p.id] = actual !== undefined && actual !== null ? actual : porPrenda;
    }
    this.costosManuales.set(nuevo);
  }

  setCostoManual(id: number, valor: number | null): void {
    this.costosManuales.update((m) => ({ ...m, [id]: valor }));
    this.onCostoCambia();
  }

  onCostoCambia(): void {
    // El usuario tocó un costo manual; el resto se recalcula con lo sobrante.
    const total = this.costoRepartible();
    const prendas = this.prendasSeleccionadas();
    const manuales = this.costosManuales();
    const sumaManual = prendas
      .filter((p) => manuales[p.id] !== null && manuales[p.id] !== undefined && (manuales[p.id] ?? 0) > 0)
      .reduce((acc, p) => acc + (manuales[p.id] ?? 0), 0);
    const sinManual = prendas.filter(
      (p) => manuales[p.id] === null || manuales[p.id] === undefined || (manuales[p.id] ?? 0) <= 0,
    );

    if (sinManual.length === 0) {
      this.costosManuales.set({ ...manuales });
      return;
    }

    const sobrante = Math.max(0, Math.round((total - sumaManual) * 100) / 100);
    const porPrenda = Math.round((sobrante / sinManual.length) * 100) / 100;
    const nuevo = { ...manuales };
    for (const p of sinManual) {
      nuevo[p.id] = porPrenda;
    }
    this.costosManuales.set(nuevo);
  }

  costoRepartible(): number {
    return (this.form.costo_total ?? 0) + (this.form.gastos_extra ?? 0);
  }

  /**
   * Cantidad de prendas vendibles del fardo: total menos descartadas. Si no se
   * cargó un total, se usan las prendas seleccionadas.
   */
  prendasVendibles(): number {
    const total = this.form.prendas_total ?? 0;
    const descartadas = this.form.prendas_descartadas ?? 0;
    if (total <= 0) {
      return Math.max(1, this.prendasSeleccionadas().length);
    }
    return Math.max(1, total - descartadas);
  }

  /** Costo por prenda que resulta del reparto (para mostrar antes de guardar). */
  costoPorPrenda(): number {
    const divisor = this.prendasVendibles();
    return divisor > 0 ? Math.round((this.costoRepartible() / divisor) * 100) / 100 : 0;
  }

  /**
   * Avisos previos a guardar: si las prendas vendibles no coinciden con las
   * prendas cargadas, o si hay descartadas sin indicar el total.
   */
  avisosFardo(): string[] {
    const avisos: string[] = [];
    const total = this.form.prendas_total ?? 0;
    const descartadas = this.form.prendas_descartadas ?? 0;
    const cargadas = this.prendasSeleccionadas().length;

    if (total > 0 && descartadas > total) {
      avisos.push('Las prendas descartadas no pueden superar el total del fardo.');
    }
    if (total > 0 && this.prendasVendibles() !== cargadas) {
      avisos.push(
        `El fardo indica ${this.prendasVendibles()} prendas vendibles pero cargaste ${cargadas}. ` +
          'Revisá el total, las descartadas o las prendas del lote.',
      );
    }
    if (total === 0 && descartadas > 0) {
      avisos.push('Indicá el total de prendas del fardo para calcular bien el costo por prenda.');
    }

    return avisos;
  }

  gananciaPotencial(producto: Producto): number {
    const costo = this.costosManuales()[producto.id] ?? 0;
    return Number(producto.precio) - costo;
  }

  guardar(): void {
    if (this.guardando()) {
      return;
    }

    const f = this.form;
    if (!f.proveedor_id) {
      this.errorFormulario.set('Seleccioná el proveedor.');
      return;
    }
    if (!f.fecha) {
      this.errorFormulario.set('Indicá la fecha de la compra.');
      return;
    }
    if (f.costo_total === null || f.costo_total < 0) {
      this.errorFormulario.set('Indicá el costo total del lote.');
      return;
    }
    const prendas = this.prendasSeleccionadas();
    if (prendas.length === 0) {
      this.errorFormulario.set('Agregá al menos una prenda al lote.');
      return;
    }

    const manuales = this.costosManuales();
    const productos: CompraPrendaItem[] = prendas.map((p) => ({
      producto_id: p.id,
      costo_asignado: manuales[p.id] ?? null,
    }));

    const datos: CompraDatos = {
      proveedor_id: f.proveedor_id,
      fecha: f.fecha,
      costo_total: f.costo_total,
      gastos_extra: f.gastos_extra ?? 0,
      prendas_total: f.prendas_total ?? 0,
      prendas_descartadas: f.prendas_descartadas ?? 0,
      nota: f.nota.trim() || null,
      productos,
    };

    this.errorFormulario.set('');
    this.guardando.set(true);

    const editando = this.editando();
    const peticion = editando
      ? this.comprasService.actualizar(editando.id, datos)
      : this.comprasService.crear(datos);

    peticion.subscribe({
      next: () => {
        this.guardando.set(false);
        this.toast.success(editando ? 'Compra actualizada.' : 'Compra registrada.');
        this.cerrarModal();
        this.cargar();
      },
      error: (err) => {
        this.guardando.set(false);
        this.errorFormulario.set(this.extraerMensaje(err, 'No se pudo guardar la compra.'));
      },
    });
  }

  verDetalle(compra: Compra): void {
    this.detalle.set(compra);
    this.detalleAbierto.set(true);
    this.cargandoDetalle.set(true);

    this.comprasService.detalle(compra.id).subscribe({
      next: (res) => {
        this.cargandoDetalle.set(false);
        if (res.success && res.data) {
          this.detalle.set(res.data);
        }
      },
      error: (err) => {
        this.cargandoDetalle.set(false);
        this.toast.error(err.message ?? 'No se pudo cargar la compra.');
      },
    });
  }

  cerrarDetalle(): void {
    this.detalleAbierto.set(false);
    this.detalle.set(null);
  }

  detalleProductos(compra: Compra): CompraProductoDetalle[] {
    return compra.detalle_precios ?? compra.productos ?? [];
  }

  /** Prendas vendibles del fardo de una compra ya guardada. */
  vendiblesCompra(compra: Compra): number {
    if (typeof compra.prendas_vendibles === 'number' && compra.prendas_vendibles > 0) {
      return compra.prendas_vendibles;
    }
    const total = compra.prendas_total ?? 0;
    const descartadas = compra.prendas_descartadas ?? 0;
    if (total <= 0) {
      return compra.prendas_count ?? 0;
    }
    return Math.max(1, total - descartadas);
  }

  pedirAnular(compra: Compra): void {
    this.anularObjetivo = compra;
  }

  cancelarAnular(): void {
    this.anularObjetivo = null;
  }

  confirmarAnular(): void {
    const compra = this.anularObjetivo;
    if (!compra) {
      return;
    }

    this.anularObjetivo = null;
    this.procesandoId = compra.id;

    this.comprasService.anular(compra.id).subscribe({
      next: () => {
        this.procesandoId = null;
        this.toast.success('Compra anulada.');
        this.cargar();
      },
      error: (err) => {
        this.procesandoId = null;
        this.toast.error(this.extraerMensaje(err, 'No se pudo anular la compra.'));
      },
    });
  }

  pedirEliminar(compra: Compra): void {
    this.eliminarObjetivo = compra;
  }

  cancelarEliminar(): void {
    this.eliminarObjetivo = null;
  }

  confirmarEliminar(): void {
    const compra = this.eliminarObjetivo;
    if (!compra) {
      return;
    }

    this.eliminarObjetivo = null;
    this.procesandoId = compra.id;

    this.comprasService.eliminar(compra.id).subscribe({
      next: () => {
        this.procesandoId = null;
        this.toast.success('Compra eliminada.');
        this.cargar();
      },
      error: (err) => {
        this.procesandoId = null;
        this.toast.error(this.extraerMensaje(err, 'No se pudo eliminar la compra.'));
      },
    });
  }

  proveedorNombre(compra: Compra): string {
    return compra.proveedor?.nombre ?? '—';
  }

  puedeEliminar(compra: Compra): boolean {
    return (compra.vendidas_count ?? 0) === 0;
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
