import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  FuenteMovimiento,
  FlujoCaja,
  Gasto,
  Movimiento,
  ObjetivoEliminar,
  PeriodoFlujo,
  SaldoCaja,
  TipoMovimiento,
} from '../../core/models/caja';
import { Paginated } from '../../core/models/paginated';
import { CategoriaGastoRef } from '../../core/models/caja';
import { MetodoPagoRef } from '../../core/models/pago';
import { CajaService } from '../../core/services/caja.service';
import { PagosService } from '../../core/services/pagos.service';
import { ToastService } from '../../core/services/toast.service';
import { ApiError } from '../../core/models/api-response';
import { BadgeComponent } from '../../shared/components/badge/badge';
import { ChartBarsComponent } from '../../shared/components/chart-bars/chart-bars';
import { ChartDato } from '../../shared/components/chart.models';
import { ConfirmDialogComponent } from '../../shared/components/confirm-dialog/confirm-dialog';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state';
import { NotificationCenterComponent } from '../../shared/components/notification-center/notification-center';
import { PaginatorComponent } from '../../shared/components/paginator/paginator';
import { SpinnerComponent } from '../../shared/components/spinner/spinner';
import { StatCardComponent } from '../../shared/components/stat-card/stat-card';
import { IngresoManualModalComponent } from './ingreso-manual-modal';
import { RegistroGastoModalComponent } from './registro-gasto-modal';

@Component({
  imports: [
    FormsModule,
    BadgeComponent,
    ChartBarsComponent,
    ConfirmDialogComponent,
    EmptyStateComponent,
    NotificationCenterComponent,
    PaginatorComponent,
    SpinnerComponent,
    StatCardComponent,
    RegistroGastoModalComponent,
    IngresoManualModalComponent,
  ],
  selector: 'app-caja',
  standalone: true,
  styleUrl: './caja.scss',
  templateUrl: './caja.html',
})
export class CajaComponent implements OnInit {
  private readonly cajaService = inject(CajaService);
  private readonly pagosService = inject(PagosService);
  private readonly toast = inject(ToastService);

  readonly saldo = signal<SaldoCaja | null>(null);
  readonly flujo = signal<FlujoCaja | null>(null);
  readonly cargandoFlujo = signal(false);
  readonly periodoFlujo = signal<PeriodoFlujo>('ultimos_30_dias');
  readonly filtros = signal({
    busqueda: '',
    tipo: '' as '' | TipoMovimiento,
    fecha_desde: '',
    fecha_hasta: '',
  });
  readonly metodos = signal<MetodoPagoRef[]>([]);
  readonly categorias = signal<CategoriaGastoRef[]>([]);
  readonly resultados = signal<Paginated<Movimiento> | null>(null);
  readonly cargando = signal(true);
  readonly pagina = signal(1);
  readonly perPage = 15;

  registrandoGasto = false;
  registrandoIngreso = false;
  objetivoEliminar: ObjetivoEliminar | null = null;
  eliminando = false;

  ngOnInit(): void {
    this.cargarCatalogo();
    this.cargarTodo();
  }

  cambiarPeriodoFlujo(): void {
    this.cargarFlujo();
  }

  onBuscar(): void {
    this.pagina.set(1);
    this.cargar();
  }

  onPagina(nueva: number): void {
    this.pagina.set(nueva);
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtros.set({ busqueda: '', tipo: '', fecha_desde: '', fecha_hasta: '' });
    this.onBuscar();
  }

  abrirGasto(): void {
    this.registrandoGasto = true;
  }

  cerrarGasto(): void {
    this.registrandoGasto = false;
  }

  onGastoRegistrado(_gasto: Gasto): void {
    this.registrandoGasto = false;
    this.cargarTodo();
  }

  abrirIngreso(): void {
    this.registrandoIngreso = true;
  }

  cerrarIngreso(): void {
    this.registrandoIngreso = false;
  }

  onIngresoRegistrado(_movimiento: Movimiento): void {
    this.registrandoIngreso = false;
    this.cargarTodo();
  }

  pedirEliminar(movimiento: Movimiento): void {
    this.objetivoEliminar = {
      id: movimiento.id,
      origen: movimiento.fuente === 'gasto' ? 'gasto' : 'movimiento',
      descripcion: movimiento.descripcion,
      monto: movimiento.monto,
    };
  }

  cancelarEliminar(): void {
    this.objetivoEliminar = null;
  }

  eliminar(): void {
    const objetivo = this.objetivoEliminar;
    if (!objetivo || this.eliminando) {
      return;
    }

    this.eliminando = true;
    const operacion =
      objetivo.origen === 'gasto'
        ? this.cajaService.eliminarGasto(objetivo.id)
        : this.cajaService.eliminarMovimiento(objetivo.id);

    operacion.subscribe({
      next: () => {
        this.eliminando = false;
        this.objetivoEliminar = null;
        this.toast.success(objetivo.origen === 'gasto' ? 'Gasto eliminado.' : 'Movimiento eliminado.');
        this.cargarTodo();
      },
      error: (err: ApiError) => {
        this.eliminando = false;
        this.toast.error(err.message ?? 'No se pudo eliminar.');
      },
    });
  }

  puedeEliminar(movimiento: Movimiento): boolean {
    return movimiento.fuente !== 'pago';
  }

  private cargarCatalogo(): void {
    this.pagosService.metodosPago().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.metodos.set(res.data);
        }
      },
      error: () => undefined,
    });

    this.cajaService.categoriasGasto().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.categorias.set(res.data);
        }
      },
      error: () => undefined,
    });
  }

  private cargarTodo(): void {
    this.cargarSaldo();
    this.cargarFlujo();
    this.cargar();
  }

  private cargarFlujo(): void {
    this.cargandoFlujo.set(true);

    this.cajaService.flujo(this.periodoFlujo()).subscribe({
      next: (res) => {
        this.cargandoFlujo.set(false);
        if (res.success && res.data) {
          this.flujo.set(res.data);
        }
      },
      error: () => {
        this.cargandoFlujo.set(false);
      },
    });
  }

  chartIngresos(): ChartDato[] {
    return (this.flujo()?.dias ?? []).map((d) => ({
      etiqueta: this.etiquetaFechaCorta(d.fecha),
      valor: parseFloat(d.ingresos),
    }));
  }

  chartEgresos(): ChartDato[] {
    return (this.flujo()?.dias ?? []).map((d) => ({
      etiqueta: this.etiquetaFechaCorta(d.fecha),
      valor: parseFloat(d.egresos),
    }));
  }

  saldoFinPeriodo(): string | null {
    const dias = this.flujo()?.dias ?? [];
    return dias.length > 0 ? dias[dias.length - 1].saldo : null;
  }

  private cargarSaldo(): void {
    this.cajaService.saldo().subscribe({
      next: (res) => {
        if (res.success && res.data) {
          this.saldo.set(res.data);
        }
      },
      error: () => undefined,
    });
  }

  private cargar(): void {
    const f = this.filtros();

    this.cargando.set(true);

    this.cajaService
      .listar({
        busqueda: f.busqueda || undefined,
        tipo: f.tipo || null,
        fecha_desde: f.fecha_desde || null,
        fecha_hasta: f.fecha_hasta || null,
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
          this.toast.error(err.message ?? 'No se pudieron cargar los movimientos.');
        },
      });
  }

  etiquetaTipo(tipo: TipoMovimiento): string {
    return tipo === 'ingreso' ? 'Ingreso' : 'Egreso';
  }

  tonoTipo(tipo: TipoMovimiento): string {
    return tipo === 'ingreso' ? 'success' : 'danger';
  }

  etiquetaFuente(fuente: FuenteMovimiento): string {
    return fuente === 'pago' ? 'Pago' : fuente === 'gasto' ? 'Gasto' : 'Manual';
  }

  moneda(valor: string | number | null | undefined): string {
    const numero = typeof valor === 'number' ? valor : parseFloat(String(valor ?? '0'));
    return `Bs ${Number.isNaN(numero) ? '0.00' : numero.toFixed(2)}`;
  }

  formatearFecha(fecha: string | null | undefined): string {
    if (!fecha) {
      return '—';
    }
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map((n) => Number(n));
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-AR');
  }

  etiquetaFechaCorta(fecha: string): string {
    return fecha.slice(8, 10) + '/' + fecha.slice(5, 7);
  }
}